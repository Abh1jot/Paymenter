<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Repositories;

use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordSyncLog;

class SyncLogRepository
{
    public function log(int $userId, string $discordUserId, ?string $guildId, string $action, array $rolesAdded = [], array $rolesRemoved = [], ?string $details = null, string $status = 'success'): DiscordSyncLog
    {
        return DiscordSyncLog::create([
            'user_id' => $userId,
            'discord_user_id' => $discordUserId,
            'guild_id' => $guildId,
            'action' => $action,
            'roles_added' => $rolesAdded,
            'roles_removed' => $rolesRemoved,
            'details' => $details,
            'status' => $status,
        ]);
    }
}
