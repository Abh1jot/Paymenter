<?php

namespace Paymenter\Extensions\Servers\Webuzo;

use App\Classes\Extension\Server;
use App\Models\Service;
use App\Rules\Domain;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Webuzo server extension for Paymenter.
 *
 * Auth: apiuser + apikey as URL query params (same pattern as Virtualizor extension).
 * Ref: https://github.com/clientexec/webuzo-server/blob/master/WebuzoApi.php
 */
class Webuzo extends Server
{
    /**
     * Make an API request to the Webuzo Admin panel.
     * Modelled exactly on Virtualizor extension pattern.
     */
    private function request(string $act, string $method = 'get', array $data = []): array
    {
        $host    = rtrim($this->config('host'), '/');
        $apiuser = $this->config('username');
        $apikey  = $this->config('apikey');

        $url = $host . '/index.php?api=json&act=' . $act
            . '&apiuser=' . $apiuser
            . '&apikey=' . $apikey
            . '&skip_callback=1';

        if ($method === 'get') {
            if (!empty($data)) {
                $url .= '&' . http_build_query($data);
            }
            $response = Http::withoutVerifying()->get($url)->throw();
        } else {
            $response = Http::withoutVerifying()->asForm()->post($url, $data)->throw();
        }

        $body   = $response->body();
        $result = json_decode($body, true);

        if (!is_array($result)) {
            $result = @unserialize($body);
        }

        if (!is_array($result)) {
            $snippet = substr(strip_tags($body), 0, 300);
            throw new Exception('Webuzo returned invalid response: ' . $snippet);
        }

        if (!empty($result['error'])) {
            $errors = is_array($result['error']) ? implode(', ', $result['error']) : $result['error'];
            throw new Exception('Webuzo error: ' . $errors);
        }

        return $result;
    }

    public function getConfig($values = []): array
    {
        return [
            [
                'name'        => 'host',
                'type'        => 'text',
                'label'       => 'Webuzo Panel URL',
                'placeholder' => 'https://your-server-ip:2005',
                'required'    => true,
            ],
            [
                'name'     => 'username',
                'type'     => 'text',
                'label'    => 'API Username',
                'required' => true,
            ],
            [
                // Do NOT use encrypted=>true — Extension::config() uses ->pluck() which
                // bypasses Eloquent model events, so encrypted values are never decrypted.
                // All other extensions (Virtualizor, Plesk) also store API keys as plain text.
                'name'        => 'apikey',
                'type'        => 'text',
                'label'       => 'API Key',
                'placeholder' => 'Generate from Webuzo Admin → Settings → API Keys',
                'required'    => true,
            ],
        ];
    }

    public function testConfig(): bool|string
    {
        try {
            $this->request('users');

            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getProductConfig($values = []): array
    {
        $result = $this->request('plans');

        // Response: {"plans": {"slug": {"plan_name": "Display Name", ...}, ...}}
        $options = [];
        if (!empty($result['plans']) && is_array($result['plans'])) {
            foreach ($result['plans'] as $slug => $plan) {
                $options[$slug] = $plan['plan_name'] ?? $slug;
            }
        }

        return [
            [
                'name'     => 'plan',
                'type'     => 'select',
                'label'    => 'Hosting Plan',
                'options'  => $options,
                'required' => true,
            ],
        ];
    }

    public function getCheckoutConfig(): array
    {
        return [
            [
                'name'        => 'domain',
                'type'        => 'text',
                'label'       => 'Domain',
                'required'    => true,
                'validation'  => [new Domain, 'required'],
                'placeholder' => 'example.com',
            ],
        ];
    }

    public function createServer(Service $service, $settings, $properties): bool
    {
        $username = strtolower('u' . Str::random(7));
        $password = Str::random(16);
        $plan     = $settings['plan'] ?? null;

        $postData = [
            'create_user'     => 1,
            'user'            => $username,
            'user_passwd'     => $password,
            'cnf_user_passwd' => $password,
            'domain'          => $properties['domain'],
            'email'           => $service->user->email,
        ];

        if ($plan) {
            $postData['plan']            = $plan;
            $postData['billing_prefill'] = 1;
        }

        $this->request('add_user', 'post', $postData);

        $service->properties()->updateOrCreate(
            ['key' => 'webuzo_username'],
            ['name' => 'Webuzo Username', 'value' => $username]
        );
        $service->properties()->updateOrCreate(
            ['key' => 'webuzo_password'],
            ['name' => 'Webuzo Password', 'value' => $password]
        );
        $service->properties()->updateOrCreate(
            ['key' => 'webuzo_domain'],
            ['name' => 'Domain', 'value' => $properties['domain']]
        );

        return true;
    }

    public function suspendServer(Service $service, $settings, $properties): bool
    {
        if (!isset($properties['webuzo_username'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('users', 'post', ['suspend' => $properties['webuzo_username']]);

        return true;
    }

    public function unsuspendServer(Service $service, $settings, $properties): bool
    {
        if (!isset($properties['webuzo_username'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('users', 'post', ['unsuspend' => $properties['webuzo_username']]);

        return true;
    }

    public function terminateServer(Service $service, $settings, $properties): bool
    {
        if (!isset($properties['webuzo_username'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('users', 'post', ['delete_user' => $properties['webuzo_username']]);

        $service->properties()->whereIn('key', ['webuzo_username', 'webuzo_password', 'webuzo_domain'])->delete();

        return true;
    }

    public function upgradeServer(Service $service, $settings, $properties): bool
    {
        if (!isset($properties['webuzo_username'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('add_user', 'post', [
            'edit_user'      => 1,
            'user'           => $properties['webuzo_username'],
            'plan'           => $settings['plan'],
            'billing_prefill' => 1,
        ]);

        return true;
    }

    public function getLoginUrl(Service $service, $settings, $properties): string
    {
        if (!isset($properties['webuzo_username'])) {
            throw new Exception('Service has not been created');
        }

        $result = $this->request('sso', 'post', ['user' => $properties['webuzo_username']]);

        if (!empty($result['done']['url'])) {
            return $result['done']['url'];
        }

        // Fallback: enduser panel is on port 2003 (SSL) or 2002
        $host = rtrim($this->config('host'), '/');

        return str_replace([':2005', ':2004'], [':2003', ':2002'], $host);
    }

    public function getActions(Service $service, $settings, $properties): array
    {
        if (!isset($properties['webuzo_username'])) {
            return [];
        }

        return [
            [
                'label'    => 'Access Webuzo',
                'type'     => 'button',
                'function' => 'getLoginUrl',
            ],
        ];
    }
}
