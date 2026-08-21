<?php

namespace Paymenter\Extensions\Others\CustomFees;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Html;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource;
use Paymenter\Extensions\Others\CustomFees\Models\Fee;

#[ExtensionMeta(
    name: 'Custom Fees',
    description: 'Add custom percentage fees to specific products or entire categories, displaying breakdown at checkout and on invoices.',
    version: '1.0.0',
    author: 'Azion Cloud',
    icon: 'ri-percent-line'
)]
class CustomFees extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString('Configure product and category fees by visiting <a class="text-primary-600 font-semibold" href="' . FeeResource::getUrl() . '">Custom Fees Management</a>.'),
            ],
        ];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/CustomFees/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/CustomFees/database/migrations');
    }

    public function boot()
    {
        // Dynamic Eloquent relations on Product and Category models
        Product::resolveRelationUsing('fees', function ($product) {
            return $product->morphToMany(Fee::class, 'feeable');
        });

        Category::resolveRelationUsing('fees', function ($category) {
            return $category->morphToMany(Fee::class, 'feeable');
        });

        // Automatically inject $fees into all checkout views across ALL themes
        View::composer(['products.checkout', '*products.checkout*', '*checkout*'], function ($view) {
            $data = $view->getData();
            if (isset($data['product']) && $data['product'] instanceof Product) {
                $product = $data['product'];
                $total = $data['total'] ?? null;
                $baseSubtotal = (float) ($total?->subtotal ?: ($total?->price ?? 0));

                $applicableFees = Fee::forProduct($product);
                $feeList = [];
                foreach ($applicableFees as $fee) {
                    $feeAmount = $fee->calculateFee($baseSubtotal);
                    $feeList[] = [
                        'name' => $fee->name,
                        'rate' => (float) $fee->rate,
                        'amount' => $feeAmount,
                        'formatted_rate' => rtrim(rtrim(number_format($fee->rate, 2), '0'), '.') . '%',
                    ];
                }

                $view->with('fees', $feeList);
                $view->with('applicableFees', $applicableFees);
            }
        });

        Event::listen('permissions', function () {
            return [
                'admin.custom_fees.view' => 'View Custom Fees',
                'admin.custom_fees.create' => 'Create Custom Fees',
                'admin.custom_fees.update' => 'Update Custom Fees',
                'admin.custom_fees.delete' => 'Delete Custom Fees',
            ];
        });
    }
}
