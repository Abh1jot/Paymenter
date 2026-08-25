<?php

namespace Paymenter\Extensions\Servers\Webuzo;

use App\Classes\Extension\Server;
use App\Models\Service;
use App\Rules\Domain;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Webuzo extends Server
{
    /**
     * Make an API request to the Webuzo Admin panel.
     *
     * @param  string  $act     The Webuzo API action (e.g. 'add_user', 'users', 'plans')
     * @param  string  $method  HTTP method ('get' or 'post')
     * @param  array   $data    POST data to send
     * @return array
     *
     * @throws Exception
     */
    private function request(string $act, string $method = 'get', array $data = []): array
    {
        $host = rtrim($this->config('host'), '/');
        $url = $host . '/index.php?api=json&act=' . $act;

        $response = Http::withBasicAuth(
            $this->config('username'),
            $this->config('password')
        )->withOptions([
            'verify' => false,
        ])->$method($url, $data);

        if (!$response->successful()) {
            throw new Exception('Webuzo API request failed: HTTP ' . $response->status());
        }

        $result = $response->json();

        if (!is_array($result)) {
            throw new Exception('Webuzo API returned an invalid response');
        }

        // Check for errors in the response
        if (isset($result['error']) && !empty($result['error'])) {
            $errors = is_array($result['error']) ? implode(', ', $result['error']) : $result['error'];
            throw new Exception('Webuzo API error: ' . $errors);
        }

        return $result;
    }

    /**
     * Get the server configuration fields for the admin panel.
     *
     * @param  array  $values
     * @return array
     */
    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'host',
                'type' => 'text',
                'label' => 'Webuzo Panel URL',
                'placeholder' => 'https://your-server-ip:2005',
                'validation' => 'url:http,https',
                'required' => true,
            ],
            [
                'name' => 'username',
                'type' => 'text',
                'label' => 'Admin Username',
                'placeholder' => 'root',
                'required' => true,
            ],
            [
                'name' => 'password',
                'type' => 'password',
                'label' => 'Admin Password',
                'required' => true,
                'encrypted' => true,
            ],
        ];
    }

    /**
     * Get product configuration fields.
     * Fetches available plans from the Webuzo server.
     *
     * @param  array  $values
     * @return array
     */
    public function getProductConfig($values = []): array
    {
        $result = $this->request('plans');

        $planOptions = [];
        if (isset($result['plans']) && is_array($result['plans'])) {
            foreach ($result['plans'] as $planName => $planData) {
                $planOptions[] = [
                    'value' => $planName,
                    'label' => $planName,
                ];
            }
        }

        return [
            [
                'name' => 'plan',
                'type' => 'select',
                'label' => 'Hosting Plan',
                'options' => $planOptions,
                'required' => true,
            ],
        ];
    }

    /**
     * Get checkout configuration fields shown to customers.
     *
     * @return array
     */
    public function getCheckoutConfig(): array
    {
        return [
            [
                'name' => 'domain',
                'type' => 'text',
                'label' => 'Domain',
                'required' => true,
                'validation' => [new Domain, 'required'],
                'placeholder' => 'example.com',
            ],
        ];
    }

    /**
     * Test the server configuration by making a simple API call.
     *
     * @return bool|string
     */
    public function testConfig(): bool|string
    {
        try {
            $this->request('users');

            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Create a new hosting account on the Webuzo server.
     *
     * @param  Service  $service
     * @param  array    $settings    Product settings
     * @param  array    $properties  Checkout options
     * @return bool
     *
     * @throws Exception
     */
    public function createServer(Service $service, $settings, $properties): bool
    {
        // Generate a random username (8 chars, starts with a letter)
        $username = 'u' . strtolower(Str::random(7));
        // Generate a secure password
        $password = Str::random(16);

        $plan = $properties['plan'] ?? $settings['plan'] ?? null;

        $postData = [
            'create_user' => 1,
            'user' => $username,
            'user_passwd' => $password,
            'cnf_user_passwd' => $password,
            'domain' => $properties['domain'],
            'email' => $service->user->email,
        ];

        // Assign plan if provided, otherwise use default
        if ($plan) {
            $postData['plan'] = $plan;
            $postData['billing_prefill'] = 1;
        } else {
            $postData['prefill_default'] = 1;
        }

        $result = $this->request('add_user', 'post', $postData);

        // Store the username and password as service properties
        $service->properties()->updateOrCreate(
            ['key' => 'webuzo_username'],
            [
                'name' => 'Webuzo Username',
                'value' => $username,
            ]
        );

        $service->properties()->updateOrCreate(
            ['key' => 'webuzo_password'],
            [
                'name' => 'Webuzo Password',
                'value' => $password,
            ]
        );

        $service->properties()->updateOrCreate(
            ['key' => 'webuzo_domain'],
            [
                'name' => 'Domain',
                'value' => $properties['domain'],
            ]
        );

        return true;
    }

    /**
     * Suspend a hosting account on the Webuzo server.
     *
     * @param  Service  $service
     * @param  array    $settings    Product settings
     * @param  array    $properties  Checkout options
     * @return bool
     *
     * @throws Exception
     */
    public function suspendServer(Service $service, $settings, $properties): bool
    {
        if (!isset($properties['webuzo_username'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('users', 'post', [
            'suspend' => $properties['webuzo_username'],
        ]);

        return true;
    }

    /**
     * Unsuspend a hosting account on the Webuzo server.
     *
     * @param  Service  $service
     * @param  array    $settings    Product settings
     * @param  array    $properties  Checkout options
     * @return bool
     *
     * @throws Exception
     */
    public function unsuspendServer(Service $service, $settings, $properties): bool
    {
        if (!isset($properties['webuzo_username'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('users', 'post', [
            'unsuspend' => $properties['webuzo_username'],
        ]);

        return true;
    }

    /**
     * Terminate (delete) a hosting account on the Webuzo server.
     *
     * @param  Service  $service
     * @param  array    $settings    Product settings
     * @param  array    $properties  Checkout options
     * @return bool
     *
     * @throws Exception
     */
    public function terminateServer(Service $service, $settings, $properties): bool
    {
        if (!isset($properties['webuzo_username'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('users', 'post', [
            'delete_user' => $properties['webuzo_username'],
        ]);

        // Clean up service properties
        $service->properties()->where('key', 'webuzo_username')->delete();
        $service->properties()->where('key', 'webuzo_password')->delete();
        $service->properties()->where('key', 'webuzo_domain')->delete();

        return true;
    }

    /**
     * Upgrade a hosting account to a new plan on the Webuzo server.
     *
     * @param  Service  $service
     * @param  array    $settings    Product settings
     * @param  array    $properties  Checkout options
     * @return bool
     *
     * @throws Exception
     */
    public function upgradeServer(Service $service, $settings, $properties): bool
    {
        if (!isset($properties['webuzo_username'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('add_user', 'post', [
            'edit_user' => 1,
            'user_name' => $properties['webuzo_username'],
            'user' => $properties['webuzo_username'],
            'plan' => $settings['plan'],
            'billing_prefill' => 1,
        ]);

        return true;
    }

    /**
     * Generate a SSO login URL for the Webuzo enduser panel.
     *
     * @param  Service  $service
     * @param  array    $settings    Product settings
     * @param  array    $properties  Checkout options
     * @return string
     *
     * @throws Exception
     */
    public function getLoginUrl(Service $service, $settings, $properties): string
    {
        if (!isset($properties['webuzo_username'])) {
            throw new Exception('Service has not been created');
        }

        $result = $this->request('sso', 'post', [
            'user' => $properties['webuzo_username'],
        ]);

        if (isset($result['done']['url'])) {
            return $result['done']['url'];
        }

        // Fallback: construct a direct login URL to the enduser panel
        $host = rtrim($this->config('host'), '/');
        // Replace admin port 2005 with enduser port 2003 if present
        $enduserHost = str_replace(':2005', ':2003', $host);

        return $enduserHost;
    }

    /**
     * Get available actions for the service (shown to customers).
     *
     * @param  Service  $service
     * @param  array    $settings    Product settings
     * @param  array    $properties  Checkout options
     * @return array
     */
    public function getActions(Service $service, $settings, $properties): array
    {
        if (!isset($properties['webuzo_username'])) {
            return [];
        }

        return [
            [
                'label' => 'Access Webuzo',
                'type' => 'button',
                'function' => 'getLoginUrl',
            ],
        ];
    }
}
