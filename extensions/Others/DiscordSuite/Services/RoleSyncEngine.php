<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Services;

use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;
use Paymenter\Extensions\Others\DiscordSuite\Repositories\GuildRepository;
use Paymenter\Extensions\Others\DiscordSuite\Repositories\RoleMappingRepository;
use Paymenter\Extensions\Others\DiscordSuite\Repositories\SyncLogRepository;

class RoleSyncEngine
{
    public function __construct(
        protected DiscordRoleService $roleService,
        protected RoleMappingRepository $roleMappingRepository,
        protected GuildRepository $guildRepository,
        protected SyncLogRepository $syncLogRepository
    ) {}

    /**
     * Synchronizes roles for a specific linked Discord account across all relevant guilds.
     */
    public function syncAccount(LinkedDiscordAccount $account): array
    {
        $user = $account->user;
        if (!$user) {
            return ['status' => 'failed', 'message' => 'User not found'];
        }

        $activeServices = $user->services()
            ->where('status', Service::STATUS_ACTIVE)
            ->with(['product.category'])
            ->get();

        // 'total' is a PHP accessor (price * quantity on items), not a real DB column.
        // Sum it at the DB level by joining invoice_items.
        $paidInvoicesSum = (float) DB::table('invoices')
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.user_id', $user->id)
            ->where('invoices.status', Invoice::STATUS_PAID)
            ->sum(DB::raw('invoice_items.price * invoice_items.quantity'));

        $guilds = $this->guildRepository->getAll();
        if ($guilds->isEmpty()) {
            // If no guilds configured in discord_guilds table, check mappings for distinct guild_ids
            $guildIds = $this->roleMappingRepository->getActiveMappings()->pluck('guild_id')->unique();
        } else {
            $guildIds = $guilds->pluck('guild_id');
        }

        $syncResults = [];

        foreach ($guildIds as $guildId) {
            try {
                $result = $this->syncUserForGuild($user, $account, (string) $guildId, $activeServices, $paidInvoicesSum);
                $syncResults[$guildId] = $result;
            } catch (Exception $e) {
                Log::error("Role sync exception for user {$user->id} in guild {$guildId}: " . $e->getMessage());
                $this->syncLogRepository->log(
                    $user->id,
                    $account->discord_user_id,
                    $guildId,
                    'error',
                    [],
                    [],
                    $e->getMessage(),
                    'failed'
                );
                $syncResults[$guildId] = ['status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        $account->update(['last_synced_at' => now()]);

        return $syncResults;
    }

    /**
     * Synchronize roles for a user in a specific guild.
     */
    public function syncUserForGuild(
        User $user,
        LinkedDiscordAccount $account,
        string $guildId,
        Collection $activeServices,
        float $paidInvoicesSum
    ): array {
        $mappings = $this->roleMappingRepository->getActiveMappings($guildId);
        $managedRoleIds = $this->roleMappingRepository->getAllManagedRoleIds($guildId);

        if ($mappings->isEmpty()) {
            return ['status' => 'no_rules', 'message' => 'No active role rules for guild'];
        }

        // 1. Calculate desired roles
        $desiredRoleIds = collect();

        foreach ($mappings as $rule) {
            $qualifies = false;

            if ($rule->rule_type === 'product') {
                $qualifies = $activeServices->contains(fn ($service) => $service->product_id == $rule->target_id);
            } elseif ($rule->rule_type === 'category') {
                $qualifies = $activeServices->contains(fn ($service) => $service->product?->category_id == $rule->target_id);
            } elseif ($rule->rule_type === 'customer_tier') {
                $qualifies = match ($rule->tier_type) {
                    'verified' => $user->email_verified_at !== null || DB::table('invoices')->where('user_id', $user->id)->where('status', Invoice::STATUS_PAID)->exists(),
                    'active' => $activeServices->isNotEmpty(),
                    'premium' => $paidInvoicesSum >= ($rule->min_spend ?: 100),
                    'vps' => $activeServices->contains(fn ($s) =>
                        str_contains(strtolower($s->product?->name ?? ''), 'vps') ||
                        str_contains(strtolower($s->product?->category?->name ?? ''), 'vps') ||
                        str_contains(strtolower($s->product?->name ?? ''), 'cloud')
                    ),
                    'dedicated' => $activeServices->contains(fn ($s) =>
                        str_contains(strtolower($s->product?->name ?? ''), 'dedicated') ||
                        str_contains(strtolower($s->product?->category?->name ?? ''), 'dedicated') ||
                        str_contains(strtolower($s->product?->name ?? ''), 'bare metal')
                    ),
                    'minecraft' => $activeServices->contains(fn ($s) =>
                        str_contains(strtolower($s->product?->name ?? ''), 'minecraft') ||
                        str_contains(strtolower($s->product?->category?->name ?? ''), 'minecraft') ||
                        str_contains(strtolower($s->product?->name ?? ''), 'mc')
                    ),
                    default => false,
                };
            }

            if ($qualifies) {
                $desiredRoleIds->push($rule->discord_role_id);
            }
        }

        $desiredRoleIds = $desiredRoleIds->unique()->values()->toArray();

        // 2. Fetch current member roles from Discord
        $currentRoles = $this->roleService->getMemberRoles($guildId, $account->discord_user_id);

        // 3. Compute Delta
        // Roles to Add: In desired, but not currently in Discord
        $rolesToAdd = array_values(array_diff($desiredRoleIds, $currentRoles));

        // Roles to Remove: In current Discord roles, IN MANAGED ROLES, but not in desired
        $rolesToRemove = array_values(array_diff(
            array_intersect($currentRoles, $managedRoleIds),
            $desiredRoleIds
        ));

        // 4. Execute Changes
        $added = [];
        $removed = [];

        foreach ($rolesToAdd as $roleId) {
            if ($this->roleService->addRole($guildId, $account->discord_user_id, $roleId)) {
                $added[] = $roleId;
            }
        }

        foreach ($rolesToRemove as $roleId) {
            if ($this->roleService->removeRole($guildId, $account->discord_user_id, $roleId)) {
                $removed[] = $roleId;
            }
        }

        // 5. Audit Logging
        $action = 'no_change';
        if (!empty($added) && !empty($removed)) {
            $action = 'roles_added_and_removed';
        } elseif (!empty($added)) {
            $action = 'roles_added';
        } elseif (!empty($removed)) {
            $action = 'roles_removed';
        }

        $this->syncLogRepository->log(
            $user->id,
            $account->discord_user_id,
            $guildId,
            $action,
            $added,
            $removed,
            "Desired: " . count($desiredRoleIds) . " roles. Added: " . count($added) . ", Removed: " . count($removed),
            'success'
        );

        return [
            'status' => 'success',
            'action' => $action,
            'roles_added' => $added,
            'roles_removed' => $removed,
            'desired_roles_count' => count($desiredRoleIds),
        ];
    }
}
