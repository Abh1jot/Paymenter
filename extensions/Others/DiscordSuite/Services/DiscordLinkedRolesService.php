<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Services;

use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordLinkedRole;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;

class DiscordLinkedRolesService
{
    public function __construct(
        protected DiscordApiService $apiService,
        protected DiscordOAuthService $oauthService
    ) {}

    /**
     * Registers the application role connection metadata schema with Discord.
     */
    public function registerMetadataSchema(): array
    {
        $schema = [
            [
                'key' => 'active_services',
                'name' => 'Active Services',
                'description' => 'Number of currently active services with provider',
                'type' => 2, // INTEGER_GREATER_THAN_OR_EQUAL
            ],
            [
                'key' => 'total_spent',
                'name' => 'Total Spent ($)',
                'description' => 'Total amount paid across all invoices',
                'type' => 2, // INTEGER_GREATER_THAN_OR_EQUAL
            ],
            [
                'key' => 'account_age_days',
                'name' => 'Account Age (Days)',
                'description' => 'Days since customer account registration',
                'type' => 2, // INTEGER_GREATER_THAN_OR_EQUAL
            ],
            [
                'key' => 'invoices_paid',
                'name' => 'Invoices Paid',
                'description' => 'Count of completed invoices',
                'type' => 2, // INTEGER_GREATER_THAN_OR_EQUAL
            ],
            [
                'key' => 'is_verified',
                'name' => 'Verified Customer',
                'description' => 'Email verified or has paid at least 1 invoice',
                'type' => 7, // BOOLEAN_EQUAL
            ],
        ];

        return $this->apiService->registerRoleConnectionMetadata($schema);
    }

    /**
     * Calculates customer attributes and pushes them to Discord Linked Roles API.
     */
    public function syncUserRoleConnection(LinkedDiscordAccount $account): array
    {
        $user = $account->user;
        if (!$user) {
            return ['status' => 'failed', 'message' => 'User not found'];
        }

        // Refresh access token if expired
        $accessToken = $account->access_token;
        if ($account->isTokenExpired() && $account->refresh_token) {
            try {
                $tokenData = $this->oauthService->refreshToken($account->refresh_token);
                $account->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? $account->refresh_token,
                    'token_expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
                ]);
                $accessToken = $tokenData['access_token'];
            } catch (Exception $e) {
                Log::warning("Failed refreshing token for Linked Roles sync: " . $e->getMessage());
                return ['status' => 'token_expired', 'message' => 'Failed to refresh token'];
            }
        }

        // Calculate metadata
        $activeServicesCount = $user->services()->where('status', Service::STATUS_ACTIVE)->count();
        $invoicesPaidCount = $user->invoices()->where('status', Invoice::STATUS_PAID)->count();
        $totalSpent = (int) round((float) $user->invoices()->where('status', Invoice::STATUS_PAID)->sum('total'));
        $accountAgeDays = (int) $user->created_at->diffInDays(now());
        $isVerified = ($user->email_verified_at !== null || $invoicesPaidCount > 0) ? 1 : 0;
        $supportLevel = $totalSpent > 500 ? 3 : ($totalSpent > 100 ? 2 : 1);

        // Update local cache
        DiscordLinkedRole::updateOrCreate(
            ['user_id' => $user->id],
            [
                'discord_user_id' => $account->discord_user_id,
                'active_services' => $activeServicesCount,
                'total_spent' => $totalSpent,
                'account_age_days' => $accountAgeDays,
                'invoices_paid' => $invoicesPaidCount,
                'is_verified' => (bool) $isVerified,
                'support_level' => $supportLevel,
                'synced_at' => now(),
            ]
        );

        $payload = [
            'platform_name' => config('app.name', 'Paymenter'),
            'platform_username' => $user->name,
            'metadata' => [
                'active_services' => $activeServicesCount,
                'total_spent' => $totalSpent,
                'account_age_days' => $accountAgeDays,
                'invoices_paid' => $invoicesPaidCount,
                'is_verified' => $isVerified,
            ],
        ];

        try {
            $result = $this->apiService->updateUserRoleConnection($accessToken, $payload);
            $account->update(['linked_roles_synced_at' => now()]);

            return ['status' => 'success', 'data' => $result];
        } catch (Exception $e) {
            Log::warning("Failed updating user role connection on Discord: " . $e->getMessage());
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }
}
