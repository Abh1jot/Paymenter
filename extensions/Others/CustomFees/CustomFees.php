<?php

namespace Paymenter\Extensions\Others\CustomFees;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Category;
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
        // 1. Dynamic Eloquent relations on Product and Category models
        Product::resolveRelationUsing('fees', function ($product) {
            return $product->morphToMany(Fee::class, 'feeable');
        });

        Category::resolveRelationUsing('fees', function ($category) {
            return $category->morphToMany(Fee::class, 'feeable');
        });

        // 2. Register lightweight API route for fee data
        $this->registerFeeApiRoute();

        // 3. Inject fees display via the footer hook (works on EVERY theme)
        // CRITICAL: Paymenter hook() / renderEvent() requires returning ['view' => '<html>']
        Event::listen('footer', function () {
            try {
                if (!Schema::hasTable('fees') || !Schema::hasTable('feeables')) {
                    return null;
                }
            } catch (\Exception $e) {
                return null;
            }

            $path = request()->path();
            if (!str_contains($path, 'checkout')) {
                return null;
            }

            $feesApiUrl = url('/custom-fees/api/product-fees');

            return [
                'view' => '<script data-extension="custom-fees">
(function() {
    "use strict";
    var path = window.location.pathname;
    if (path.indexOf("checkout") === -1) return;

    var API_URL = "' . $feesApiUrl . '";
    var lastSlug = null;
    var feesCache = null;

    function getSlugs() {
        var parts = path.split("/").filter(Boolean);
        var ci = parts.indexOf("checkout");
        if (ci < 1) return null;
        return { product: parts[ci - 1], category: ci >= 2 ? parts[ci - 2] : "" };
    }

    function formatRate(r) {
        var s = parseFloat(r).toFixed(2).replace(/0+$/, "").replace(/\.$/, "");
        return s + "%";
    }

    function fetchAndInject() {
        var slugs = getSlugs();
        if (!slugs) return;

        if (slugs.product === lastSlug && feesCache) {
            injectFees(feesCache);
            return;
        }

        fetch(API_URL + "?product=" + encodeURIComponent(slugs.product) + "&category=" + encodeURIComponent(slugs.category))
            .then(function(r) { return r.json(); })
            .then(function(fees) {
                feesCache = fees;
                lastSlug = slugs.product;
                injectFees(fees);
            })
            .catch(function() {});
    }

    function injectFees(fees) {
        if (!fees || !fees.length) return;

        // Remove previous injections
        var old = document.querySelectorAll(".custom-fees-injected");
        for (var i = 0; i < old.length; i++) old[i].remove();

        // Find checkout button
        var btns = document.querySelectorAll("button");
        var checkoutBtn = null;
        for (var i = 0; i < btns.length; i++) {
            var wc = btns[i].getAttribute("wire:click");
            if (wc && wc.indexOf("checkout") !== -1) {
                checkoutBtn = btns[i];
                break;
            }
        }
        if (!checkoutBtn) return;

        // Walk up to find the summary container
        var container = checkoutBtn;
        for (var i = 0; i < 10; i++) {
            container = container.parentElement;
            if (!container) return;
            var headings = container.querySelectorAll("h2, h3, h4");
            if (headings.length > 0) break;
        }
        if (!container) return;

        // Find total row (usually has text-lg class)
        var totalRow = container.querySelector(".text-lg");
        if (!totalRow) {
            // fallback: insert before button parent
            totalRow = checkoutBtn.closest("div");
        }
        if (!totalRow) return;

        // Create fee rows and insert before total
        var frag = document.createDocumentFragment();
        fees.forEach(function(fee) {
            var div = document.createElement("div");
            div.className = "custom-fees-injected font-semibold flex justify-between text-sm";
            div.style.cssText = "padding: 2px 0;";
            div.innerHTML = "<span>" + fee.name + " (" + formatRate(fee.rate) + "):</span><span>" + fee.formatted_amount + "</span>";
            frag.appendChild(div);
        });

        totalRow.parentElement.insertBefore(frag, totalRow);
    }

    // Initial run
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", fetchAndInject);
    } else {
        setTimeout(fetchAndInject, 200);
    }

    // Re-run after Livewire morphs (plan/option changes)
    document.addEventListener("livewire:init", function() {
        if (window.Livewire) {
            Livewire.hook("morph.updated", function() {
                setTimeout(fetchAndInject, 200);
            });
        }
    });

    // Fallback observer
    var obsTimer = null;
    var obs = new MutationObserver(function() {
        clearTimeout(obsTimer);
        obsTimer = setTimeout(function() {
            if (!document.querySelector(".custom-fees-injected")) {
                fetchAndInject();
            }
        }, 300);
    });
    obs.observe(document.body, { childList: true, subtree: true });
    setTimeout(function() { obs.disconnect(); }, 30000);
})();
</script>',
            ];
        });

        // 4. Register permissions
        Event::listen('permissions', function () {
            return [
                'admin.custom_fees.view' => 'View Custom Fees',
                'admin.custom_fees.create' => 'Create Custom Fees',
                'admin.custom_fees.update' => 'Update Custom Fees',
                'admin.custom_fees.delete' => 'Delete Custom Fees',
            ];
        });
    }

    protected function registerFeeApiRoute()
    {
        $router = app('router');
        $router->get('/custom-fees/api/product-fees', function () {
            $productSlug = request()->query('product');
            $categorySlug = request()->query('category');

            if (!$productSlug) {
                return response()->json([]);
            }

            try {
                if (!Schema::hasTable('fees') || !Schema::hasTable('feeables')) {
                    return response()->json([]);
                }

                $product = null;
                if ($categorySlug) {
                    $category = Category::where('slug', $categorySlug)->first();
                    if ($category) {
                        $product = Product::where('slug', $productSlug)
                            ->where('category_id', $category->id)
                            ->first();
                    }
                }
                if (!$product) {
                    $product = Product::where('slug', $productSlug)->first();
                }
                if (!$product) {
                    return response()->json([]);
                }

                $fees = Fee::forProduct($product);
                $plan = $product->plans->first();
                $basePrice = $plan ? (float) ($plan->price()?->price ?? 0) : 0;

                return response()->json($fees->map(function ($fee) use ($basePrice, $plan) {
                    $amount = $fee->calculateFee($basePrice);
                    return [
                        'name' => $fee->name,
                        'rate' => (float) $fee->rate,
                        'amount' => $amount,
                        'formatted_amount' => $this->formatAmount($amount, $plan),
                    ];
                })->values());
            } catch (\Exception $e) {
                return response()->json([]);
            }
        })->middleware('web');
    }

    protected function formatAmount(float $amount, $plan = null): string
    {
        if (!$plan) return number_format($amount, 2);
        try {
            $price = $plan->price();
            if ($price && $price->currency) {
                $currency = $price->currency;
                if (is_array($currency)) $currency = (object) $currency;
                $formatted = number_format($amount, 2);
                return ($currency->prefix ?? '') . $formatted . ($currency->suffix ?? '');
            }
        } catch (\Exception $e) {}
        return number_format($amount, 2);
    }
}
