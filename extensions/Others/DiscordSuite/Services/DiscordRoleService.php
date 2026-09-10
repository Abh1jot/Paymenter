<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class DiscordRoleService
{
    public function __construct(protected DiscordApiService $apiService) {}

    public function getGuildRoles(string $guildId): array
    {
        return $this->apiService->getGuildRoles($guildId);
    }

    public function getMemberRoles(string $guildId, string $discordUserId): array
    {
        $member = $this->apiService->getGuildMember($guildId, $discordUserId);

        return $member['roles'] ?? [];
    }

    public function addRole(string $guildId, string $discordUserId, string $roleId): bool
    {
        try {
            return $this->apiService->addRoleToMember($guildId, $discordUserId, $roleId);
        } catch (Exception $e) {
            Log::error("Failed adding role {$roleId} to user {$discordUserId} in guild {$guildId}: " . $e->getMessage());
            return false;
        }
    }

    public function removeRole(string $guildId, string $discordUserId, string $roleId): bool
    {
        try {
            return $this->apiService->removeRoleFromMember($guildId, $discordUserId, $roleId);
        } catch (Exception $e) {
            Log::error("Failed removing role {$roleId} from user {$discordUserId} in guild {$guildId}: " . $e->getMessage());
            return false;
        }
    }
}
