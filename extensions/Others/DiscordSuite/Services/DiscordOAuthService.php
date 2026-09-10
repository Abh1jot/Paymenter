<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Services;

use App\Helpers\ExtensionHelper;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class DiscordOAuthService
{
    protected ?string $clientId;

    protected ?string $clientSecret;

    protected string $redirectUri;

    public function __construct(protected DiscordApiService $apiService)
    {
        $this->clientId = $this->apiService->getClientId();
        $this->clientSecret = $this->apiService->getClientSecret();
        $this->redirectUri = url('/discord-suite/oauth/callback');
    }

    public function getAuthorizationUrl(User $user, ?string $customRedirect = null): string
    {
        if (!$this->clientId) {
            throw new Exception('Discord Client ID is missing. Configure it in Discord Suite admin settings.');
        }

        $state = Str::random(40);
        Session::put('discord_suite_oauth_state', $state);
        Session::put('discord_suite_oauth_user_id', $user->id);

        $redirectUri = $customRedirect ?: $this->redirectUri;
        $scopes = ['identify', 'email', 'guilds', 'guilds.join', 'role_connections.write'];

        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'prompt' => 'consent',
        ]);

        return "https://discord.com/api/oauth2/authorize?{$params}";
    }

    public function validateState(string $state): bool
    {
        $savedState = Session::pull('discord_suite_oauth_state');

        return $savedState && hash_equals($savedState, $state);
    }

    public function exchangeCodeForToken(string $code, ?string $customRedirect = null): array
    {
        if (!$this->clientId || !$this->clientSecret) {
            throw new Exception('Discord OAuth credentials (Client ID / Client Secret) are not configured.');
        }

        $redirectUri = $customRedirect ?: $this->redirectUri;

        $response = Http::asForm()->post('https://discord.com/api/oauth2/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        if ($response->failed()) {
            throw new Exception('Discord OAuth token exchange failed: ' . $response->body());
        }

        return $response->json();
    }

    public function refreshToken(string $refreshToken): array
    {
        if (!$this->clientId || !$this->clientSecret) {
            throw new Exception('Discord OAuth credentials (Client ID / Client Secret) are not configured.');
        }

        $response = Http::asForm()->post('https://discord.com/api/oauth2/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        if ($response->failed()) {
            throw new Exception('Discord OAuth token refresh failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getUserInfo(string $accessToken): array
    {
        $response = $this->apiService->get('/users/@me', [], $accessToken, isUserToken: true);
        if ($response->failed()) {
            throw new Exception('Failed to fetch Discord user information: ' . $response->body());
        }

        return $response->json();
    }
}
