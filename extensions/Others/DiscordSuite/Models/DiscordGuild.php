<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscordGuild extends Model
{
    protected $table = 'discord_guilds';

    protected $fillable = [
        'guild_id',
        'name',
        'icon',
        'invite_url',
        'auto_join_enabled',
        'welcome_channel_id',
        'welcome_message',
        'bot_present',
    ];

    protected function casts(): array
    {
        return [
            'auto_join_enabled' => 'boolean',
            'bot_present' => 'boolean',
        ];
    }

    public function roleMappings(): HasMany
    {
        return $this->hasMany(DiscordRoleMapping::class, 'guild_id', 'guild_id');
    }

    public function iconUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!empty($this->icon)) {
                    $isAnimated = str_starts_with($this->icon, 'a_');
                    $format = $isAnimated ? 'gif' : 'png';
                    return "https://cdn.discordapp.com/icons/{$this->guild_id}/{$this->icon}.{$format}?size=128";
                }

                return null;
            }
        );
    }
}
