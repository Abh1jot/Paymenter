<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordGuild;

class GuildRepository
{
    public function getAll(): Collection
    {
        return DiscordGuild::all();
    }

    public function getAutoJoinGuilds(): Collection
    {
        return DiscordGuild::where('auto_join_enabled', true)->get();
    }

    public function findByGuildId(string $guildId): ?DiscordGuild
    {
        return DiscordGuild::where('guild_id', $guildId)->first();
    }

    public function createOrUpdate(string $guildId, array $data): DiscordGuild
    {
        return DiscordGuild::updateOrCreate(
            ['guild_id' => $guildId],
            $data
        );
    }
}
