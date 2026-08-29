<?php

namespace Paymenter\Extensions\Others\CustomFees;

use App\Attributes\ExtensionMeta;
use App\Classes\Cart as ClassesCart;
use App\Classes\Extension\Extension;
use App\Events\InvoiceItem\Created as InvoiceItemCreated;
use App\Helpers\ExtensionHelper;
use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource;
use Paymenter\Extensions\Others\CustomFees\Models\Fee;

#[ExtensionMeta(
    name: 'Custom Fees',
    description: 'Add custom percentage fees to specific products or entire categories, automatically displaying fee breakdowns on checkout and invoices across all themes.',
    version: '1.1.0',
    author: 'Azion Cloud',
    icon: 'ri-percent-line'
)]
class CustomFees extends Extension
{
    /**
     * Guard flag to prevent infinite recursion when creating fee invoice items.
     */
    private static bool $addingFeeItems = false;

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

        // 2. View Composer: inject $cartFees into the cart view for ALL themes
        $this->registerCartFeeComposer();

        // 3. Listen for InvoiceItem creation to add fee line items on invoices
        $this->registerInvoiceItemListener();

        // 4. Keep the lightweight API route as fallback for themes that use JS
        $this->registerFeeApiRoute();

        // 5. Register permissions
        Event::listen('permissions', function () {
            return [
                'admin.custom_fees.view' => 'View Custom Fees',
                'admin.custom_fees.create' => 'Create Custom Fees',
                'admin.custom_fees.update' => 'Update Custom Fees',
                'admin.custom_fees.delete' => 'Delete Custom Fees',
            ];
        });
    }

    /**
     * Register a View Composer that injects $cartFees into the cart view.
     * This works with ANY theme that renders the 'cart' view.
     */
    protected function registerCartFeeComposer()
    {
        View::composer('cart', function ($view) {
            try {
                if (!Schema::hasTable('fees') || !Schema::hasTable('feeables')) {
                    $view->with('cartFees', []);
                    return;
                }

                $cartItems = ClassesCart::items();
                if (!$cartItems || $cartItems->isEmpty()) {
                    $view->with('cartFees', []);
                    return;
                }

                // Collect all fees across all cart items, aggregated by fee ID
                $aggregatedFees = [];

                foreach ($cartItems as $item) {
                    $product = $item->product;
                    if (!$product) continue;

                    $fees = Fee::forProduct($product);
                    if ($fees->isEmpty()) continue;

                    // Get the item's base price (before fees)
                    $basePrice = (float) ($item->price->price ?? 0);

                    foreach ($fees as $fee) {
                        $feeAmount = $fee->calculateFee($basePrice) * $item->quantity;

                        if (isset($aggregatedFees[$fee->id])) {
                            $aggregatedFees[$fee->id]['amount'] += $feeAmount;
                        } else {
                            $aggregatedFees[$fee->id] = [
                                'name' => $fee->name,
                                'rate' => (float) $fee->rate,
                                'amount' => $feeAmount,
                            ];
                        }
                    }
                }

                $view->with('cartFees', array_values($aggregatedFees));
            } catch (\Exception $e) {
                $view->with('cartFees', []);
            }
        });
    }

    /**
     * Listen for InvoiceItem\Created events and add fee line items.
     * This covers:
     *   - New order checkout (Cart.php)
     *   - Recurring/renewal invoices (CronJob.php)
     *   - Admin-created orders (CreateOrder.php)
     *   - Service upgrades (Upgrade.php)
     */
    protected function registerInvoiceItemListener()
    {
        Event::listen(InvoiceItemCreated::class, function (InvoiceItemCreated $event) {
            // Guard: prevent infinite recursion when we create fee items
            if (self::$addingFeeItems) {
                return;
            }

            try {
                if (!Schema::hasTable('fees') || !Schema::hasTable('feeables')) {
                    return;
                }

                $invoiceItem = $event->invoiceItem;

                // Only process invoice items that reference a Service
                if ($invoiceItem->reference_type !== Service::class) {
                    return;
                }

                $service = Service::find($invoiceItem->reference_id);
                if (!$service || !$service->product) {
                    return;
                }

                $fees = Fee::forProduct($service->product);
                if ($fees->isEmpty()) {
                    return;
                }

                $invoice = $invoiceItem->invoice;
                if (!$invoice) {
                    return;
                }

                // Base price for fee calculation = the invoice item's price
                $basePrice = (float) $invoiceItem->price;

                // Set guard flag before creating fee items
                self::$addingFeeItems = true;

                try {
                    foreach ($fees as $fee) {
                        $feeAmount = $fee->calculateFee($basePrice);
                        if ($feeAmount <= 0) continue;

                        $invoice->items()->create([
                            // IMPORTANT: Use Fee::class (NOT Service::class) as reference_type.
                            // ProcessPaidInvoiceService iterates all items where reference_type = Service::class
                            // and calls RenewServiceService for each one. Previously using Service::class here
                            // caused RenewServiceService to fire once per fee item PLUS once for the plan item,
                            // pushing expires_at forward by an extra billing period per fee. Bug: our extension.
                            'reference_id'   => $fee->id,
                            'reference_type' => Fee::class,
                            'price'          => $feeAmount,
                            'quantity'       => $invoiceItem->quantity,
                            'description'    => $fee->name . ' (' . rtrim(rtrim(number_format($fee->rate, 2), '0'), '.') . '%)',
                        ]);
                    }
                } finally {
                    // Always reset the guard flag
                    self::$addingFeeItems = false;
                }
            } catch (\Exception $e) {
                self::$addingFeeItems = false;
                report($e);
            }
        });
    }

    /**
     * Register lightweight API route for fee data (fallback for JS-based themes).
     */
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
