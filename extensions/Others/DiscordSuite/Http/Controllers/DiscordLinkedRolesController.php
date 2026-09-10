<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncLinkedRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Repositories\LinkedAccountRepository;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordOAuthService;

class DiscordLinkedRolesController extends Controller
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
            $customRedirect = url('/discord-suite/linked-roles/callback');
            $url = $this->oauthService->getAuthorizationUrl($user, $customRedirect);

            return redirect()->away($url);
        } catch (Exception $e) {
            Log::error("Discord Linked Roles redirect error: " . $e->getMessage());

            return redirect()->route('account')->with('error', 'Failed to connect Discord Linked Roles: ' . $e->getMessage());
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

        if (!$code || !$state || !$this->oauthService->validateState($state)) {
            return redirect()->route('account')->with('error', 'Invalid Discord Linked Roles authorization.');
        }

        try {
            $customRedirect = url('/discord-suite/linked-roles/callback');
            $tokenData = $this->oauthService->exchangeCodeForToken($code, $customRedirect);
            $discordUser = $this->oauthService->getUserInfo($tokenData['access_token']);

            $account = $this->linkedAccountRepository->createOrUpdate($user, $discordUser, $tokenData);
            SyncLinkedRolesJob::dispatch($user->id);

            return redirect()->route('account')->with('success', 'Discord Linked Roles successfully verified and synced!');
        } catch (Exception $e) {
            Log::error("Discord Linked Roles callback error: " . $e->getMessage());

            return redirect()->route('account')->with('error', 'Failed to verify Discord Linked Roles: ' . $e->getMessage());
        }
    }
}
