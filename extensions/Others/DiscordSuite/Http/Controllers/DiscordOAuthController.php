<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\AutoJoinGuildJob;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncLinkedRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncUserRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Repositories\LinkedAccountRepository;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordOAuthService;

class DiscordOAuthController extends Controller
{
    public function __construct(
        protected DiscordOAuthService $oauthService,
        protected LinkedAccountRepository $linkedAccountRepository
    ) {}

    public function redirect(): RedirectResponse
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        try {
            $url = $this->oauthService->getAuthorizationUrl($user);

            return redirect()->away($url);
        } catch (Exception $e) {
            Log::error("Discord OAuth redirect error: " . $e->getMessage());

            return redirect()->route('discord-suite.account.settings')->with('error', 'Failed initiating Discord connection: ' . $e->getMessage());
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $code = $request->get('code');
        $state = $request->get('state');

        if (!$code || !$state) {
            return redirect()->route('discord-suite.account.settings')->with('error', 'Discord authorization cancelled or missing parameters.');
        }

        if (!$this->oauthService->validateState($state)) {
            return redirect()->route('discord-suite.account.settings')->with('error', 'Invalid OAuth security state. Please try again.');
        }

        try {
            // 1. Exchange code for access & refresh tokens
            $tokenData = $this->oauthService->exchangeCodeForToken($code);

            // 2. Fetch Discord user profile
            $discordUser = $this->oauthService->getUserInfo($tokenData['access_token']);

            // 3. Save or update linked account record
            $account = $this->linkedAccountRepository->createOrUpdate($user, $discordUser, $tokenData);

            // 4. Dispatch background jobs: Auto-join guilds, Sync roles, Sync linked roles
            AutoJoinGuildJob::dispatch($user->id);
            SyncUserRolesJob::dispatch($user->id);
            SyncLinkedRolesJob::dispatch($user->id);

            return redirect()->route('discord-suite.account.settings')->with('success', "Discord account @{$discordUser['username']} successfully linked!");
        } catch (Exception $e) {
            Log::error("Discord OAuth callback error: " . $e->getMessage());

            return redirect()->route('discord-suite.account.settings')->with('error', 'Failed to link Discord account: ' . $e->getMessage());
        }
    }

    public function unlink(): RedirectResponse
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $account = $this->linkedAccountRepository->findByUserId($user->id);
        if ($account) {
            $this->linkedAccountRepository->deleteByUserId($user->id);

            // Trigger role sync to strip managed roles from Discord
            SyncUserRolesJob::dispatch($user->id);
        }

        return redirect()->route('discord-suite.account.settings')->with('success', 'Discord account unlinked successfully.');
    }
}
