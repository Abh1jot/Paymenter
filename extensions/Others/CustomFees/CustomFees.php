<?php

namespace Paymenter\Extensions\Others\CustomFees;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource;
use Paymenter\Extensions\Others\CustomFees\Models\Fee;

#[ExtensionMeta(
    name: 'Custom Fees',
    description: 'Add custom percentage fees to specific products or entire categories, automatically displaying fee breakdowns on checkout and invoices across all themes.',
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
                'label' => new HtmlString('Configure product and category fees by visiting <a class="text-primary-600 font-semibold" href="' . FeeResource::getUrl() . '">Custom Fees Management</a> in the Configuration menu.'),
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
        // 1. Dynamic Eloquent relations on Product and Category models without touching core files
        Product::resolveRelationUsing('fees', function ($product) {
            return $product->morphToMany(Fee::class, 'feeable');
        });

        Category::resolveRelationUsing('fees', function ($category) {
            return $category->morphToMany(Fee::class, 'feeable');
        });

        // 2. Automatically inject $fees into all checkout and invoice views across ALL themes
        View::composer(['products.checkout', '*products.checkout*', '*checkout*', 'invoices.show', '*invoices.show*'], function ($view) {
            $data = $view->getData();
            if (isset($data['product']) && $data['product'] instanceof Product) {
                $product = $data['product'];
                $total = $data['total'] ?? null;
                $baseSubtotal = (float) ($total?->subtotal ?: ($total?->price ?? 0));

                $applicableFees = Fee::forProduct($product);
                $feeList = [];
                $totalFee = 0;
                foreach ($applicableFees as $fee) {
                    $feeAmount = $fee->calculateFee($baseSubtotal);
                    $totalFee += $feeAmount;
                    $feeList[] = [
                        'name' => $fee->name,
                        'rate' => (float) $fee->rate,
                        'amount' => $feeAmount,
                        'formatted_rate' => rtrim(rtrim(number_format($fee->rate, 2), '0'), '.') . '%',
                    ];
                }

                $view->with('fees', $feeList);
                $view->with('applicableFees', $applicableFees);
                $view->with('totalFeeAmount', $totalFee);
            }
        });

        // 3. Universal DOM Injection via footer hook (Works on EVERY theme automatically)
        Event::listen('footer', function () {
            if (!Schema::hasTable('fees') || !Schema::hasTable('feeables')) {
                return null;
            }

            // Checkout Page Auto-Injection
            if (request()->routeIs('products.checkout') || str_contains(request()->path(), 'checkout')) {
                $product = request()->route('product');
                if (!$product instanceof Product && is_numeric($product)) {
                    $product = Product::find($product);
                }

                if ($product) {
                    $fees = Fee::forProduct($product);
                    if ($fees->isNotEmpty()) {
                        $feePayload = json_encode($fees->map(fn ($f) => [
                            'name' => $f->name,
                            'rate' => (float) $f->rate,
                        ]));

                        return new HtmlString(<<<HTML
<script data-extension="custom-fees">
(function() {
    const fees = {$feePayload};
    if (!fees || !fees.length) return;

    function injectFees() {
        // If the theme already rendered fees via Blade, don't duplicate
        if (document.querySelector('.custom-fee-item') || document.querySelector('[data-custom-fee]')) return;

        // Find checkout summary container on any theme
        const summaryBtn = document.querySelector('[wire\\:click*="checkout"]') || document.querySelector('button[wire\\:click="checkout"]') || document.querySelector('[wire\\:target="checkout"]');
        if (!summaryBtn) return;

        const summaryBox = summaryBtn.closest('.bg-background-secondary') || summaryBtn.closest('.rounded-2xl') || summaryBtn.closest('.shadow-2xl') || summaryBtn.parentElement;
        if (!summaryBox || summaryBox.querySelector('.custom-fees-container')) return;

        const feeWrapper = document.createElement('div');
        feeWrapper.className = 'custom-fees-container my-2 space-y-1.5 pt-2 border-t border-white/10';

        fees.forEach(fee => {
            const row = document.createElement('div');
            row.className = 'custom-fee-item flex justify-between items-center text-sm text-secondary py-0.5';
            row.setAttribute('data-custom-fee', fee.name);
            row.innerHTML = `<span class="font-medium text-color-muted">\${fee.name} (\${fee.rate}%):</span><span class="font-semibold text-primary">Included</span>`;
            feeWrapper.appendChild(row);
        });

        // Insert before checkout button or total row
        summaryBtn.parentElement.insertBefore(feeWrapper, summaryBtn);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectFees);
    } else {
        injectFees();
    }

    document.addEventListener('livewire:initialized', () => {
        if (window.Livewire) {
            window.Livewire.hook('commit', () => {
                setTimeout(injectFees, 100);
            });
        }
    });
})();
</script>
HTML);
                    }
                }
            }

            return null;
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
