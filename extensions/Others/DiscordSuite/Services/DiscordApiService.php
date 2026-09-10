<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Services;

use App\Helpers\ExtensionHelper;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordApiService
{
    protected string $baseUrl = 'https://discord.com/api/v10';

    protected ?string $botToken = null;

    protected ?string $clientId = null;

    protected ?string $clientSecret = null;

    public function __construct()
    {
        $extension = ExtensionHelper::getExtension('other', 'DiscordSuite');
        if ($extension) {
            $this->botToken = $extension->config('bot_token');
            $this->clientId = $extension->config('client_id');
            $this->clientSecret = $extension->config('client_secret');
        }
    }

    public function getBotToken(): ?string
    {
        return $this->botToken;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    /**
     * Send HTTP request to Discord API with rate-limit and backoff handling.
     */
    public function request(string $method, string $endpoint, array $data = [], ?string $token = null, bool $isUserToken = false, int $retries = 3): Response
    {
        $token = $token ?: $this->botToken;
        if (!$token) {
            throw new Exception('Discord API Token is not configured in Discord Suite settings.');
        }

        $authHeader = $isUserToken ? "Bearer {$token}" : "Bot {$token}";
        $url = str_starts_with($endpoint, 'http') ? $endpoint : "{$this->baseUrl}/" . ltrim($endpoint, '/');

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $client = Http::withHeaders([
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Paymenter-DiscordSuite (https://paymenter.org, 1.0.0)',
                ])->timeout(15);

                $response = match (strtoupper($method)) {
                    'GET' => $client->get($url, $data),
                    'POST' => $client->post($url, $data),
                    'PUT' => $client->put($url, $data),
                    'PATCH' => $client->patch($url, $data),
                    'DELETE' => $client->delete($url, $data),
                    default => throw new Exception("Unsupported HTTP method: {$method}")
                };

                // Handle Discord Rate Limits (HTTP 429)
                if ($response->status() === 429) {
                    $retryAfter = (float) ($response->json('retry_after') ?? 1.5);
                    Log::warning("Discord API Rate Limited on {$endpoint}. Retrying after {$retryAfter} seconds. (Attempt {$attempt}/{$retries})");

                    if ($attempt < $retries) {
                        usleep((int) ($retryAfter * 1000000));
                        continue;
                    }
                }

                return $response;
            } catch (Exception $e) {
                if ($attempt >= $retries) {
                    Log::error("Discord API Request failed after {$retries} attempts: " . $e->getMessage(), [
                        'method' => $method,
                        'endpoint' => $endpoint,
                    ]);
                    throw $e;
                }
                usleep(500000 * $attempt); // 0.5s, 1s backoff
            }
        }

        throw new Exception("Discord API request exhausted {$retries} retry attempts.");
    }

    public function get(string $endpoint, array $query = [], ?string $token = null, bool $isUserToken = false): Response
    {
        return $this->request('GET', $endpoint, $query, $token, $isUserToken);
    }

    public function post(string $endpoint, array $data = [], ?string $token = null, bool $isUserToken = false): Response
    {
        return $this->request('POST', $endpoint, $data, $token, $isUserToken);
    }

    public function put(string $endpoint, array $data = [], ?string $token = null, bool $isUserToken = false): Response
    {
        return $this->request('PUT', $endpoint, $data, $token, $isUserToken);
    }

    public function patch(string $endpoint, array $data = [], ?string $token = null, bool $isUserToken = false): Response
    {
        return $this->request('PATCH', $endpoint, $data, $token, $isUserToken);
    }

    public function delete(string $endpoint, array $data = [], ?string $token = null, bool $isUserToken = false): Response
    {
        return $this->request('DELETE', $endpoint, $data, $token, $isUserToken);
    }

    // High Level Bot Methods

    public function getBotUser(): array
    {
        $response = $this->get('/users/@me');
        if ($response->failed()) {
            throw new Exception('Failed to fetch bot user: ' . $response->body());
        }

        return $response->json();
    }

    public function getGuilds(): array
    {
        $response = $this->get('/users/@me/guilds');
        if ($response->failed()) {
            throw new Exception('Failed to fetch bot guilds: ' . $response->body());
        }

        return $response->json();
    }

    public function getGuildRoles(string $guildId): array
    {
        $response = $this->get("/guilds/{$guildId}/roles");
        if ($response->failed()) {
            Log::warning("Failed to fetch roles for guild {$guildId}: " . $response->body());
            return [];
        }

        return $response->json();
    }

    public function getGuildMember(string $guildId, string $discordUserId): ?array
    {
        $response = $this->get("/guilds/{$guildId}/members/{$discordUserId}");
        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new Exception("Failed to get guild member: " . $response->body());
        }

        return $response->json();
    }

    public function addGuildMember(string $guildId, string $discordUserId, string $userAccessToken, array $roleIds = []): array
    {
        $payload = ['access_token' => $userAccessToken];
        if (!empty($roleIds)) {
            $payload['roles'] = $roleIds;
        }

        $response = $this->put("/guilds/{$guildId}/members/{$discordUserId}", $payload);

        return [
            'status' => $response->status(), // 201 = added, 204 = already member
            'data' => $response->json() ?? [],
        ];
    }

    public function addRoleToMember(string $guildId, string $discordUserId, string $roleId): bool
    {
        $response = $this->put("/guilds/{$guildId}/members/{$discordUserId}/roles/{$roleId}");

        return in_array($response->status(), [204, 200]);
    }

    public function removeRoleFromMember(string $guildId, string $discordUserId, string $roleId): bool
    {
        $response = $this->delete("/guilds/{$guildId}/members/{$discordUserId}/roles/{$roleId}");

        return in_array($response->status(), [204, 200]);
    }

    public function createDMChannel(string $recipientDiscordId): string
    {
        $response = $this->post('/users/@me/channels', [
            'recipient_id' => $recipientDiscordId,
        ]);

        if ($response->failed()) {
            throw new Exception('Failed to open DM channel: ' . $response->body());
        }

        return $response->json('id');
    }

    public function sendChannelMessage(string $channelId, array $payload): array
    {
        $response = $this->post("/channels/{$channelId}/messages", $payload);

        if ($response->failed()) {
            $code = $response->json('code');
            $msg = $response->json('message') ?? $response->body();
            throw new Exception("Failed to send message: [{$code}] {$msg}", $response->status());
        }

        return $response->json();
    }

    public function registerGlobalCommands(array $commands): array
    {
        if (!$this->clientId) {
            throw new Exception('Client ID is required to register slash commands.');
        }

        $response = $this->put("/applications/{$this->clientId}/commands", $commands);
        if ($response->failed()) {
            throw new Exception('Failed to register global slash commands: ' . $response->body());
        }

        return $response->json();
    }

    public function registerGuildCommands(string $guildId, array $commands): array
    {
        if (!$this->clientId) {
            throw new Exception('Client ID is required to register slash commands.');
        }

        $response = $this->put("/applications/{$this->clientId}/guilds/{$guildId}/commands", $commands);
        if ($response->failed()) {
            throw new Exception("Failed to register guild slash commands for {$guildId}: " . $response->body());
        }

        return $response->json();
    }

    public function registerRoleConnectionMetadata(array $metadata): array
    {
        if (!$this->clientId) {
            throw new Exception('Client ID is required to register role connection metadata.');
        }

        $response = $this->put("/applications/{$this->clientId}/role-connections/metadata", $metadata);
        if ($response->failed()) {
            throw new Exception('Failed to register role connection metadata: ' . $response->body());
        }

        return $response->json();
    }

    public function updateUserRoleConnection(string $userAccessToken, array $body): array
    {
        if (!$this->clientId) {
            throw new Exception('Client ID is required to update user role connection.');
        }

        $response = $this->put(
            "/users/@me/applications/{$this->clientId}/role-connection",
            $body,
            $userAccessToken,
            isUserToken: true
        );

        if ($response->failed()) {
            throw new Exception('Failed to update user role connection: ' . $response->body());
        }

        return $response->json() ?? [];
    }
}
