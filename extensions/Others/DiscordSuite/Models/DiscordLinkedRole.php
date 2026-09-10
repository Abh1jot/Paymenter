<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Models;

use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordLinkedRole extends Model
{
    protected $table = 'discord_linked_roles';

    protected $fillable = [
        'user_id',
        'discord_user_id',
        'active_services',
        'total_spent',
        'account_age_days',
        'invoices_paid',
        'is_verified',
        'support_level',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'active_services' => 'integer',
            'total_spent' => 'decimal:2',
            'account_age_days' => 'integer',
            'invoices_paid' => 'integer',
            'is_verified' => 'boolean',
            'support_level' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
