<?php

namespace Paymenter\Extensions\Others\CustomFees\Models;

use App\Models\Category;
use App\Models\Model;
use App\Models\Product;
use App\Models\Traits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        return round($price * $this->rate / 100, 2);
    }

    /**
     * Get all applicable fees for a given product (product-level + category-level, stacked).
     */
    public static function forProduct(Product $product)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('fees')) {
            return collect();
        }

        $productFees = static::where('enabled', true)
            ->whereHas('products', function ($q) use ($product) {
                $q->where('products.id', $product->id);
            })
            ->get();

        $categoryFees = collect();
        if ($product->category_id) {
            $categoryFees = static::where('enabled', true)
                ->whereHas('categories', function ($q) use ($product) {
                    $q->where('categories.id', $product->category_id);
                })
                ->get();
        }

        return $productFees->merge($categoryFees)->unique('id');
    }
}
