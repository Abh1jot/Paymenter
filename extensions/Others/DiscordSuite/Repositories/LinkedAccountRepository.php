<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;

class LinkedAccountRepository
{
    public function findByUserId(int $userId): ?LinkedDiscordAccount
    {
        return LinkedDiscordAccount::where('user_id', $userId)->first();
    }

    public function findByDiscordId(string $discordId): ?LinkedDiscordAccount
    {
        return LinkedDiscordAccount::where('discord_user_id', $discordId)->first();
    }

    public function createOrUpdate(User $user, array $discordUser, array $tokenData): LinkedDiscordAccount
    {
        return LinkedDiscordAccount::updateOrCreate(
            ['user_id' => $user->id],
            [
                'discord_user_id' => $discordUser['id'],
                'discord_username' => $discordUser['username'],
                'discord_discriminator' => $discordUser['discriminator'] ?? '0',
                'discord_global_name' => $discordUser['global_name'] ?? null,
                'discord_avatar' => $discordUser['avatar'] ?? null,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
                'scopes' => isset($tokenData['scope']) ? explode(' ', (string) $tokenData['scope']) : [],
                'last_synced_at' => now(),
            ]
        );
    }

    public function updateTokens(LinkedDiscordAccount $account, array $tokenData): LinkedDiscordAccount
    {
        $account->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
        ]);

        return $account;
    }

    public function deleteByUserId(int $userId): bool
    {
        return (bool) LinkedDiscordAccount::where('user_id', $userId)->delete();
    }

    public function getAllLinked(): Collection
    {
        return LinkedDiscordAccount::with('user')->get();
    }

    public function updateGuildStatus(LinkedDiscordAccount $account, string $guildId, bool $inGuild): void
    {
        $status = $account->guild_membership_status ?? [];
        $status[$guildId] = [
            'in_guild' => $inGuild,
            'updated_at' => now()->toIso8601String(),
        ];
        $account->update(['guild_membership_status' => $status]);
    }
}
