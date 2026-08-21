<?php

namespace Paymenter\Extensions\Others\CustomFees\Models;

use App\Models\Category;
use App\Models\Model;
use App\Models\Product;
use App\Models\Traits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use OwenIt\Auditing\Contracts\Auditable;

class Fee extends Model implements Auditable
{
    use HasFactory, Traits\Auditable;

    protected $fillable = ['name', 'rate', 'enabled'];

    protected $casts = [
        'rate' => 'decimal:4',
        'enabled' => 'boolean',
    ];

    /**
     * Get the products that have this fee.
     */
    public function products()
    {
        return $this->morphedByMany(Product::class, 'feeable');
    }

    /**
     * Get the categories that have this fee.
     */
    public function categories()
    {
        return $this->morphedByMany(Category::class, 'feeable');
    }

    /**
     * Calculate fee amount for a given price.
     */
    public function calculateFee(float $price): float
    {
        return round($price * ((float) $this->rate) / 100, 2);
    }

    /**
     * Get all applicable fees for a given product (product-level + category-level, stacked).
     */
    public static function forProduct(Product $product)
    {
        if (!Schema::hasTable('fees') || !Schema::hasTable('feeables')) {
            return collect();
        }

        // Direct product fees
        $productFees = static::where('enabled', true)
            ->whereHas('products', function ($q) use ($product) {
                $q->where('products.id', $product->id);
            })
            ->get();

        // Category fees (direct category and parent categories)
        $categoryFees = collect();
        if ($product->category_id) {
            $categoryIds = [$product->category_id];
            if ($product->category && $product->category->parent_id) {
                $categoryIds[] = $product->category->parent_id;
            }

            $categoryFees = static::where('enabled', true)
                ->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                })
                ->get();
        }

        return $productFees->merge($categoryFees)->unique('id');
    }
}
