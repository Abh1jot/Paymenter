<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;
use Paymenter\Extensions\Others\DiscordSuite\Repositories\GuildRepository;
use Paymenter\Extensions\Others\DiscordSuite\Repositories\LinkedAccountRepository;

class DiscordGuildService
{
    public function __construct(
        protected DiscordApiService $apiService,
        protected GuildRepository $guildRepository,
        protected LinkedAccountRepository $linkedAccountRepository
    ) {}

    /**
     * Automatically adds a linked user to all configured auto-join guilds.
     */
    public function autoJoinUser(LinkedDiscordAccount $account): array
    {
        $guilds = $this->guildRepository->getAutoJoinGuilds();
        $results = [];

        foreach ($guilds as $guild) {
            try {
                $response = $this->apiService->addGuildMember(
                    $guild->guild_id,
                    $account->discord_user_id,
                    $account->access_token
                );

                $inGuild = in_array($response['status'], [201, 204]);
                $this->linkedAccountRepository->updateGuildStatus($account, $guild->guild_id, $inGuild);

                $results[$guild->guild_id] = [
                    'success' => true,
                    'status' => $response['status'] === 201 ? 'joined' : 'already_member',
                ];
            } catch (Exception $e) {
                Log::warning("Failed to auto-join user {$account->discord_user_id} to guild {$guild->guild_id}: " . $e->getMessage());
                $results[$guild->guild_id] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Checks if a user is currently a member of a guild.
     */
    public function isMemberOfGuild(string $guildId, string $discordUserId): bool
    {
        try {
            $member = $this->apiService->getGuildMember($guildId, $discordUserId);

            return $member !== null;
        } catch (Exception $e) {
            return false;
        }
    }
}
