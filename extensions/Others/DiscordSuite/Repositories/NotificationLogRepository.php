<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Repositories;

use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordNotification;

class NotificationLogRepository
{
    public function logSent(int $userId, string $discordUserId, string $type, ?string $refType = null, ?int $refId = null, ?string $channelId = null, ?string $messageId = null): DiscordNotification
    {
        return DiscordNotification::create([
            'user_id' => $userId,
            'discord_user_id' => $discordUserId,
            'channel_id' => $channelId,
            'message_id' => $messageId,
            'type' => $type,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function logFailed(int $userId, string $discordUserId, string $type, string $status, string $errorMessage, ?string $refType = null, ?int $refId = null): DiscordNotification
    {
        return DiscordNotification::create([
            'user_id' => $userId,
            'discord_user_id' => $discordUserId,
            'type' => $type,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    public function wasNotificationSentToday(string $type, string $refType, int $refId): bool
    {
        return DiscordNotification::where('type', $type)
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->where('status', 'sent')
            ->whereDate('created_at', now()->toDateString())
            ->exists();
    }
}
