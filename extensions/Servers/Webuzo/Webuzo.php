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
 * Auth: apiuser + apikey as URL query params — same pattern as Virtualizor extension.
 * Ref: https://github.com/clientexec/webuzo-server/blob/master/WebuzoApi.php
 */
class Webuzo extends Server
{
    /**
     * Make an API request to the Webuzo Admin panel.
     * Modelled on Virtualizor extension (identical auth pattern).
     */
    private function request(string $act, string $method = 'get', array $data = []): array
    {
        $host = rtrim($this->config('host'), '/');

        $url = $host . '/index.php?api=json&act=' . $act
            . '&apiuser=' . $this->config('username')
            . '&apikey=' . $this->config('apikey')
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
            throw new Exception('Webuzo returned invalid response: ' . substr(strip_tags($body), 0, 300));
        }

        if (!empty($result['error'])) {
            $errors = is_array($result['error']) ? implode(', ', $result['error']) : $result['error'];
            throw new Exception('Webuzo error: ' . $errors);
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Server configuration (admin panel)
    // -----------------------------------------------------------------------

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
                // Do NOT add encrypted=>true — Extension::config() uses ->pluck() which
                // bypasses Eloquent model events, so encrypted values are never decrypted.
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

    // -----------------------------------------------------------------------
    // Product configuration (admin panel)
    // -----------------------------------------------------------------------

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

    // -----------------------------------------------------------------------
    // Checkout (customer-facing)
    // -----------------------------------------------------------------------

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

    // -----------------------------------------------------------------------
    // Service lifecycle
    // -----------------------------------------------------------------------

    /**
     * Create a hosting account.
     * Returns an array that Paymenter passes to the "new_server_created" email notification.
     */
    public function createServer(Service $service, $settings, $properties): array
    {
        $username = strtolower('w' . Str::random(7));
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

        // Persist credentials as service properties (shown on the service page)
        $service->properties()->updateOrCreate(
            ['key' => 'webuzo_username'],
            ['name' => 'Username', 'value' => $username]
        );
        $service->properties()->updateOrCreate(
            ['key' => 'webuzo_password'],
            ['name' => 'Password', 'value' => $password]
        );
        $service->properties()->updateOrCreate(
            ['key' => 'webuzo_domain'],
            ['name' => 'Domain', 'value' => $properties['domain']]
        );

        $host        = rtrim($this->config('host'), '/');
        $panelUrl    = str_replace([':2005', ':2004'], [':2003', ':2002'], $host);

        $service->properties()->updateOrCreate(
            ['key' => 'webuzo_panel_url'],
            ['name' => 'Control Panel URL', 'value' => $panelUrl]
        );

        // Return array is sent to the user's "server created" email notification
        return [
            'username'         => $username,
            'password'         => $password,
            'domain'           => $properties['domain'],
            'control_panel'    => $panelUrl,
        ];
    }

    /**
     * Suspend a hosting account (called on overdue invoices / manual suspend).
     */
    public function suspendServer(Service $service, $settings, $properties): bool
    {
        if (empty($properties['webuzo_username'])) {
            throw new Exception('Service has not been provisioned yet.');
        }

        $this->request('users', 'post', [
            'suspend' => $properties['webuzo_username'],
        ]);

        return true;
    }

    /**
     * Unsuspend a hosting account (called when invoice is paid / manual unsuspend).
     */
    public function unsuspendServer(Service $service, $settings, $properties): bool
    {
        if (empty($properties['webuzo_username'])) {
            throw new Exception('Service has not been provisioned yet.');
        }

        $this->request('users', 'post', [
            'unsuspend' => $properties['webuzo_username'],
        ]);

        return true;
    }

    /**
     * Terminate a hosting account (called on cancellation).
     * Deletes the account and cleans up stored properties.
     */
    public function terminateServer(Service $service, $settings, $properties): bool
    {
        if (empty($properties['webuzo_username'])) {
            // Nothing to delete — account was never provisioned
            return true;
        }

        $this->request('users', 'post', [
            'delete_user' => $properties['webuzo_username'],
        ]);

        // Clean up all stored properties
        $service->properties()->whereIn('key', [
            'webuzo_username',
            'webuzo_password',
            'webuzo_domain',
            'webuzo_panel_url',
        ])->delete();

        return true;
    }

    /**
     * Upgrade/downgrade the account's hosting plan.
     */
    public function upgradeServer(Service $service, $settings, $properties): bool
    {
        if (empty($properties['webuzo_username'])) {
            throw new Exception('Service has not been provisioned yet.');
        }

        $this->request('add_user', 'post', [
            'edit_user'      => 1,
            'user'           => $properties['webuzo_username'],
            'plan'           => $settings['plan'],
            'billing_prefill' => 1,
        ]);

        return true;
    }

    // -----------------------------------------------------------------------
    // Customer-facing actions & SSO
    // -----------------------------------------------------------------------

    /**
     * Generate a one-click SSO login URL for the Webuzo end-user panel.
     * Called when customer clicks "Access Webuzo" button.
     */
    public function getLoginUrl(Service $service, $settings, $properties): string
    {
        if (empty($properties['webuzo_username'])) {
            throw new Exception('Service has not been provisioned yet.');
        }

        try {
            $result = $this->request('sso', 'post', [
                'user' => $properties['webuzo_username'],
            ]);

            if (!empty($result['done']['url'])) {
                return $result['done']['url'];
            }
        } catch (Exception $e) {
            // SSO failed — fall back to direct panel URL
        }

        // Fallback: direct login page on end-user port (2003 SSL / 2002 non-SSL)
        $host = rtrim($this->config('host'), '/');

        return str_replace([':2005', ':2004'], [':2003', ':2002'], $host);
    }

    /**
     * Buttons shown to the customer on their service page.
     */
    public function getActions(Service $service, $settings, $properties): array
    {
        if (empty($properties['webuzo_username'])) {
            return [];
        }

        return [
            [
                'label'    => 'Access Control Panel',
                'type'     => 'button',
                'function' => 'getLoginUrl',
            ],
        ];
    }
}
