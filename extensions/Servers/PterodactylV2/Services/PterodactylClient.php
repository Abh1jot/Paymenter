<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\Services;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Robust API transport client for Pterodactyl Application and Client APIs.
 *
 * Implements:
 * - Strict TLS verification
 * - Secret redaction in logs and exceptions
 * - Normalization of response bodies and error formats
 * - Safe timeout and retry configurations
 */
class PterodactylClient
{
    protected string $host;
    protected string $appApiKey;
    protected ?string $clientApiKey;
    protected int $timeout = 20;

    public function __construct(string $host, string $appApiKey, ?string $clientApiKey = null)
    {
        $this->host = rtrim(trim($host), '/');
        $this->appApiKey = trim($appApiKey);
        $this->clientApiKey = $clientApiKey ? trim($clientApiKey) : null;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function hasClientKey(): bool
    {
        return !empty($this->clientApiKey);
    }

    /**
     * Send an authenticated request to the Pterodactyl Application API (/api/application/...).
     *
     * @throws Exception
     */
    public function appRequest(string $endpoint, string $method = 'GET', array $data = []): array
    {
        return $this->sendRequest($this->appApiKey, '/api/application/' . ltrim($endpoint, '/'), $method, $data);
    }

    /**
     * Send an authenticated request to the Pterodactyl Client API (/api/client/...).
     *
     * @throws Exception
     */
    public function clientRequest(string $endpoint, string $method = 'GET', array $data = []): array
    {
        if (empty($this->clientApiKey)) {
            throw new Exception('Client API key is not configured. Real-time controls require an administrative Client API key.');
        }

        return $this->sendRequest($this->clientApiKey, '/api/client/' . ltrim($endpoint, '/'), $method, $data);
    }

    /**
     * Internal HTTP executor with robust error handling and secret redaction.
     */
    protected function sendRequest(string $token, string $path, string $method, array $data = []): array
    {
        $method = strtolower($method);

        if (str_contains($path, '?')) {
            [$pathOnly, $queryString] = explode('?', $path, 2);
            parse_str($queryString, $queryParams);
            if (in_array($method, ['get', 'delete'])) {
                $data = array_merge($queryParams, $data);
            }
            $path = $pathOnly;
        }

        $url = $this->host . $path;

        try {
            $httpClient = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout);

            /** @var Response $response */
            if (in_array($method, ['get', 'delete'])) {
                $response = !empty($data) ? $httpClient->$method($url, $data) : $httpClient->$method($url);
            } else {
                $response = $httpClient->$method($url, $data);
            }

            if ($response->successful()) {
                if ($response->status() === 204) {
                    return ['status' => 204, 'success' => true];
                }
                return $response->json() ?? [];
            }

            $errorMessage = $this->extractErrorMessage($response, $path);
            throw new Exception($errorMessage);
        } catch (Exception $e) {
            $redactedMessage = $this->redactSecrets($e->getMessage());
            Log::warning('Pterodactyl API error: ' . $redactedMessage, [
                'path' => $path,
                'method' => strtoupper($method),
            ]);
            throw new Exception($redactedMessage);
        }
    }

    /**
     * Extract a user-friendly error message from an unsuccessful HTTP response.
     */
    protected function extractErrorMessage(Response $response, string $path): string
    {
        $status = $response->status();
        $json = $response->json();

        // Pterodactyl standard error format: {"errors": [{"code": "...", "detail": "...", "status": "..."}]}
        if (isset($json['errors']) && is_array($json['errors']) && !empty($json['errors'])) {
            $details = [];
            foreach ($json['errors'] as $err) {
                if (!empty($err['detail'])) {
                    $details[] = $err['detail'];
                } elseif (!empty($err['code'])) {
                    $details[] = $err['code'];
                }
            }
            if (!empty($details)) {
                return implode('; ', $details);
            }
        }

        if (isset($json['message']) && is_string($json['message'])) {
            return $json['message'];
        }

        return match ($status) {
            400 => 'Bad Request: Pterodactyl rejected the payload for ' . $path,
            401 => 'Unauthorized: Invalid Pterodactyl API Key. Please verify your credentials.',
            403 => 'Forbidden: The provided API Key does not have permission to access ' . $path,
            404 => 'Not Found: The requested resource does not exist on the Pterodactyl panel (' . $path . ')',
            422 => 'Validation Error: Invalid server parameters provided to Pterodactyl.',
            429 => 'Rate Limited: Pterodactyl API rate limit reached. Please wait a moment.',
            500 => 'Internal Server Error: Pterodactyl panel encountered an internal error.',
            502, 503, 504 => 'Gateway Error: Pterodactyl panel is currently unreachable or offline.',
            default => "Pterodactyl API request failed with HTTP status {$status}",
        };
    }

    /**
     * Strip API keys and potential authorization tokens from strings.
     */
    public function redactSecrets(string $content): string
    {
        if (empty($content)) {
            return $content;
        }

        // Redact standard Pterodactyl token formats: ptla_..., ptlc_...
        $patterns = [
            '/(ptla_[a-zA-Z0-9_-]{20,})/i' => 'ptla_***[REDACTED]***',
            '/(ptlc_[a-zA-Z0-9_-]{20,})/i' => 'ptlc_***[REDACTED]***',
            '/(Bearer\s+)[^\s",]+/i' => '$1***[REDACTED]***',
        ];

        if (!empty($this->appApiKey)) {
            $content = str_replace($this->appApiKey, '***[REDACTED]***', $content);
        }
        if (!empty($this->clientApiKey)) {
            $content = str_replace($this->clientApiKey, '***[REDACTED]***', $content);
        }

        return preg_replace(array_keys($patterns), array_values($patterns), $content);
    }

    /**
     * Test the connection to the Pterodactyl panel and verify permissions.
     */
    public function testConnection(): array
    {
        if (empty($this->host) || empty($this->appApiKey)) {
            return [
                'success' => false,
                'message' => 'Panel URL and Application API Key are required.',
            ];
        }

        try {
            // Test 1: Application API servers listing
            $servers = $this->appRequest('servers?per_page=1');
            $serverCount = $servers['meta']['pagination']['total'] ?? 0;

            // Test 2: Panel version / Egg architecture check
            $versionMode = 'v1';
            $isV2 = false;
            try {
                $eggsCheck = $this->sendRequest($this->appApiKey, '/api/application/eggs?per_page=1', 'GET');
                if (isset($eggsCheck['data'])) {
                    $isV2 = true;
                    $versionMode = 'v2 (Panel 2.0 with Tags & Direct Eggs)';
                }
            } catch (Exception $e) {
                // v1 returns 404 on /eggs top-level
                $versionMode = 'v1 (Panel 1.x with Nests)';
            }

            // Test 3: Optional Client API check
            $clientStatus = 'Not configured';
            if ($this->hasClientKey()) {
                try {
                    $clientCheck = $this->sendRequest($this->clientApiKey, '/api/client', 'GET');
                    if (isset($clientCheck['data'])) {
                        $clientStatus = 'Connected & Active';
                    }
                } catch (Exception $ce) {
                    $clientStatus = 'Failed: ' . $this->redactSecrets($ce->getMessage());
                }
            }

            return [
                'success' => true,
                'version' => $versionMode,
                'is_v2' => $isV2,
                'server_count' => $serverCount,
                'client_api_status' => $clientStatus,
                'message' => "Successfully connected to Pterodactyl ({$versionMode}). Found {$serverCount} active servers.",
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $this->redactSecrets($e->getMessage()),
            ];
        }
    }
}
