<?php

namespace Paymenter\Extensions\Others\MailsManager\Services;

use App\Models\Extension;

/**
 * ====================================================================
 * MailsManager for Paymenter — BuiltByBit Distribution & BitGuard Protection
 * Licensed to: %%__USERNAME__%% (BuiltByBit User ID: %%__USER__%%)
 * Resource ID: %%__RESOURCE__%% | Nonce: %%__NONCE__%%
 * Timestamp: %%__TIMESTAMP__%%
 *
 * Notice: Redistribution or unauthorized sharing is strictly tracked
 * and violates BuiltByBit Terms of Service.
 * ====================================================================
 */
class LicenseService
{
    private const RAW_USER_TOKEN = '%%' . '__USER__' . '%%';
    private const RAW_USERNAME_TOKEN = '%%' . '__USERNAME__' . '%%';
    private const RAW_RESOURCE_TOKEN = '%%' . '__RESOURCE__' . '%%';
    private const RAW_NONCE_TOKEN = '%%' . '__NONCE__' . '%%';

    // Injected placeholders evaluated at runtime
    private string $buyerId = '%%__USER__%%';
    private string $buyerUsername = '%%__USERNAME__%%';
    private string $resourceId = '%%__RESOURCE__%%';
    private string $nonce = '%%__NONCE__%%';
    private string $timestamp = '%%__TIMESTAMP__%%';

    private string $productId = 'mailsmanager-paymenter';
    private int $cacheTtlSeconds = 43200;       // 12 hours local cache
    private int $gracePeriodSeconds = 86400;   // 24 hours offline grace period

    public function __construct()
    {
        $this->loadMetadataFromFile();
    }

    /**
     * Read buyer metadata from license.json if available
     */
    private function loadMetadataFromFile(): void
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'license.json';
        if (file_exists($path)) {
            try {
                $data = json_decode(file_get_contents($path), true);
                if (is_array($data)) {
                    if (!empty($data['buyer_id'])) {
                        $this->buyerId = (string) $data['buyer_id'];
                    }
                    if (!empty($data['buyer_username'])) {
                        $this->buyerUsername = (string) $data['buyer_username'];
                    }
                    if (!empty($data['resource_id'])) {
                        $this->resourceId = (string) $data['resource_id'];
                    }
                    if (!empty($data['download_nonce'])) {
                        $this->nonce = (string) $data['download_nonce'];
                    }
                    if (!empty($data['timestamp'])) {
                        $this->timestamp = (string) $data['timestamp'];
                    }
                }
            } catch (\Throwable $e) {
                // Silently ignore corrupted file
            }
        }
    }

    /**
     * Compute unique machine hardware identifier (HWID)
     */
    public function getHardwareId(): string
    {
        $components = [
            php_uname('n'),
            php_uname('s'),
            php_uname('m'),
            $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()),
        ];

        if (file_exists('/etc/machine-id')) {
            $components[] = trim(file_get_contents('/etc/machine-id'));
        } elseif (file_exists('/var/lib/dbus/machine-id')) {
            $components[] = trim(file_get_contents('/var/lib/dbus/machine-id'));
        }

        return hash('sha256', implode(':', $components));
    }

    /**
     * Resolve verification API endpoint
     */
    private function getEndpoint(): string
    {
        $configured = $this->getSetting('bitguard_endpoint', '');
        if (!empty($configured)) {
            return rtrim($configured, '/');
        }

        return rtrim(env('BITGUARD_ENDPOINT', 'https://bitguard.azioncloud.com'), '/');
    }

    /**
     * Get buyer and licensing fingerprint data for view cards
     */
    public function getLicenseData(): array
    {
        $savedKey = $this->getSetting('license_key', '');
        $savedStatus = $this->getSetting('license_status', '');
        $verifiedAt = $this->getSetting('license_verified_at', '');
        $expiresAt = $this->getSetting('license_expires_at', '');

        $isDev = ($this->buyerId === self::RAW_USER_TOKEN || empty($this->buyerId) || $this->buyerId === '%%__USER__%%');
        $isInjected = !$isDev;

        if (empty($savedStatus)) {
            $savedStatus = $isDev ? 'developer_mode' : 'unverified';
        }

        $isValid = ($isDev || $savedStatus === 'active');

        return [
            'is_dev'              => $isDev,
            'is_injected'         => $isInjected,
            'buyer_id'            => $isDev ? 'DEVELOPER' : $this->buyerId,
            'buyer_username'      => $isDev ? 'Creator' : $this->buyerUsername,
            'resource_id'         => $isDev ? 'LOCAL' : $this->resourceId,
            'download_nonce'      => $isDev ? 'DEV-LOCAL' : (strlen($this->nonce) > 16 ? substr($this->nonce, 0, 16) . '...' : $this->nonce),
            'download_timestamp'  => $isDev ? date('Y-m-d H:i') : $this->timestamp,
            'license_key'         => $savedKey,
            'license_status'      => $savedStatus,
            'license_verified_at' => $verifiedAt,
            'license_expires_at'  => $expiresAt,
            'is_valid'            => $isValid,
        ];
    }

    /**
     * Remotely verify and store an entered license key against BitGuard
     */
    public function verifyAndSave(string $licenseKey): array
    {
        $licenseKey = trim($licenseKey);

        if (empty($licenseKey)) {
            return [
                'success' => false,
                'message' => 'Please provide a valid BitGuard / BuiltByBit License Key.',
            ];
        }

        if (strlen($licenseKey) < 5 || strlen($licenseKey) > 120) {
            return [
                'success' => false,
                'message' => 'The provided license key format is invalid.',
            ];
        }

        $endpoint = $this->getEndpoint();
        $hwid = $this->getHardwareId();
        $domain = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? gethostname();
        $ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());

        $payload = [
            'license_key' => $licenseKey,
            'product_id' => $this->productId,
            'hwid' => $hwid,
            'domain' => $domain,
            'ip' => $ip,
            'hostname' => gethostname(),
        ];

        $ch = curl_init($endpoint . '/api/verify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: BitGuard-MailsManager-Paymenter/1.0',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 500) {
            return [
                'success' => false,
                'message' => 'Could not connect to BitGuard verification server: ' . ($curlErr ?: "HTTP {$httpCode}"),
            ];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [
                'success' => false,
                'message' => 'Invalid response from verification server.',
            ];
        }

        if (!empty($data['valid'])) {
            $this->setSetting('license_key', $licenseKey);
            $this->setSetting('license_status', 'active');
            $this->setSetting('license_buyer_id', $this->buyerId);
            $this->setSetting('license_verified_at', now()->toDateTimeString());
            $this->setSetting('license_expires_at', $data['expires_at'] ?? 'Lifetime');
            $this->setSetting('license_cached_until', (string) (time() + $this->cacheTtlSeconds));

            return [
                'success' => true,
                'message' => $data['message'] ?? 'License successfully verified and activated.',
            ];
        }

        $this->setSetting('license_status', 'invalid');
        return [
            'success' => false,
            'message' => $data['message'] ?? 'Verification rejected by BitGuard server.',
        ];
    }

    /**
     * Check if the extension is authorized to execute premium actions.
     */
    public function isAuthorized(): bool
    {
        $data = $this->getLicenseData();
        return (bool) ($data['is_valid'] ?? false);
    }

    /**
     * Setting getter with Extension model and cache fallback
     */
    private function getSetting(string $key, $default = null): ?string
    {
        try {
            $ext = Extension::where('extension', 'MailsManager')->first();
            if ($ext) {
                $val = $ext->settings()->where('key', $key)->value('value');
                if ($val !== null) {
                    return (string) $val;
                }
            }
        } catch (\Throwable $e) {
            // DB not ready or migrated yet
        }

        return cache()->get('mailsmanager_' . $key, $default);
    }

    /**
     * Setting setter with Extension model and cache fallback
     */
    private function setSetting(string $key, ?string $value): void
    {
        try {
            $ext = Extension::where('extension', 'MailsManager')->first();
            if ($ext) {
                $ext->settings()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        } catch (\Throwable $e) {
            //
        }

        cache()->forever('mailsmanager_' . $key, $value);
    }
}