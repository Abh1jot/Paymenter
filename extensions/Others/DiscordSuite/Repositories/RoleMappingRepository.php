<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordRoleMapping;

class RoleMappingRepository
{
    public function getActiveMappings(?string $guildId = null): Collection
    {
        $query = DiscordRoleMapping::where('enabled', true)->orderBy('priority', 'desc');

        if ($guildId) {
            $query->where('guild_id', $guildId);
        }

        return $query->get();
    }

    public function getByProduct(int $productId, ?string $guildId = null): Collection
    {
        $query = DiscordRoleMapping::where('enabled', true)
            ->where('rule_type', 'product')
            ->where('target_id', $productId);

        if ($guildId) {
            $query->where('guild_id', $guildId);
        }

        return $query->get();
    }

    public function getByCategory(int $categoryId, ?string $guildId = null): Collection
    {
        $query = DiscordRoleMapping::where('enabled', true)
            ->where('rule_type', 'category')
            ->where('target_id', $categoryId);

        if ($guildId) {
            $query->where('guild_id', $guildId);
        }

        return $query->get();
    }

    public function getByTier(string $tierType, ?string $guildId = null): Collection
    {
        $query = DiscordRoleMapping::where('enabled', true)
            ->where('rule_type', 'customer_tier')
            ->where('tier_type', $tierType);

        if ($guildId) {
            $query->where('guild_id', $guildId);
        }

        return $query->get();
    }

    public function getAllManagedRoleIds(?string $guildId = null): array
    {
        $query = DiscordRoleMapping::query();

        if ($guildId) {
            $query->where('guild_id', $guildId);
        }

        return $query->pluck('discord_role_id')->unique()->values()->toArray();
    }
}
