<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Models;

use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LinkedDiscordAccount extends Model
{
    protected $table = 'linked_discord_accounts';

    protected $fillable = [
        'user_id',
        'discord_user_id',
        'discord_username',
        'discord_discriminator',
        'discord_global_name',
        'discord_avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'guild_membership_status',
        'linked_roles_synced_at',
        'last_synced_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'guild_membership_status' => 'array',
            'linked_roles_synced_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedRoleData(): HasOne
    {
        return $this->hasOne(DiscordLinkedRole::class, 'user_id', 'user_id');
    }

    public function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!empty($this->discord_avatar)) {
                    $isAnimated = str_starts_with($this->discord_avatar, 'a_');
                    $format = $isAnimated ? 'gif' : 'png';
                    return "https://cdn.discordapp.com/avatars/{$this->discord_user_id}/{$this->discord_avatar}.{$format}?size=128";
                }

                // Default Discord avatar based on discriminator or snowflake
                $index = $this->discord_discriminator !== '0'
                    ? ((int) $this->discord_discriminator) % 5
                    : (((int) substr($this->discord_user_id, -4)) >> 22) % 6;

                return "https://cdn.discordapp.com/embed/avatars/{$index}.png";
            }
        );
    }

    public function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->discord_global_name ?: $this->discord_username
        );
    }

    public function formattedTag(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->discord_discriminator !== '0' && !empty($this->discord_discriminator)
                ? "{$this->discord_username}#{$this->discord_discriminator}"
                : "@{$this->discord_username}"
        );
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }
}
