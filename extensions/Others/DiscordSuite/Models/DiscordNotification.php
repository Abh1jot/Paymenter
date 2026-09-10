<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Models;

use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DiscordNotification extends Model
{
    protected $table = 'discord_notifications';

    protected $fillable = [
        'user_id',
        'discord_user_id',
        'channel_id',
        'message_id',
        'type',
        'reference_type',
        'reference_id',
        'status',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
