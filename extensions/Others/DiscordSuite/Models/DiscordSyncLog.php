<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Models;

use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordSyncLog extends Model
{
    protected $table = 'discord_sync_logs';

    protected $fillable = [
        'user_id',
        'discord_user_id',
        'guild_id',
        'action',
        'roles_added',
        'roles_removed',
        'details',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'roles_added' => 'array',
            'roles_removed' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
