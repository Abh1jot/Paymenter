<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Models;

use App\Models\Category;
use App\Models\Model;
use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordRoleMapping extends Model
{
    protected $table = 'discord_role_mappings';

    protected $fillable = [
        'guild_id',
        'rule_type',
        'target_id',
        'tier_type',
        'discord_role_id',
        'discord_role_name',
        'priority',
        'min_spend',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'min_spend' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(DiscordGuild::class, 'guild_id', 'guild_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_id');
    }

    public function getTargetNameAttribute(): string
    {
        if ($this->rule_type === 'product') {
            return $this->product?->name ?? "Product #{$this->target_id}";
        }

        if ($this->rule_type === 'category') {
            return $this->category?->name ?? "Category #{$this->target_id}";
        }

        return match ($this->tier_type) {
            'verified' => 'Verified Customer',
            'active' => 'Active Customer',
            'premium' => 'Premium Customer' . ($this->min_spend ? " (>${$this->min_spend})" : ''),
            'vps' => 'VPS Customer',
            'dedicated' => 'Dedicated Server Customer',
            'minecraft' => 'Minecraft Customer',
            default => ucfirst((string) $this->tier_type),
        };
    }
}
