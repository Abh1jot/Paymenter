<?php

namespace Paymenter\Extensions\Others\BulkRecalculate\Admin\Pages;

use App\Models\Service;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class RecalculatePricesPage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-refresh-line';

    protected static ?string $navigationLabel = 'Recalculate Prices';

    protected static ?string $title = 'Bulk Recalculate Service Prices';

    protected static ?int $navigationSort = 50;

    protected string $view = 'bulk-recalculate::page';

    public $recentLogs = [];

    public function recalculateAll()
    {
        $services = Service::whereIn('status', ['active', 'suspended'])
            ->with(['product', 'plan.prices', 'configs.configValue', 'coupon', 'currency'])
            ->get();

        $updated = [];
        $unchanged = 0;

        foreach ($services as $service) {
            $oldPrice = (float) $service->price;
            $newPrice = (float) $service->calculatePrice();

            $formattedOldPrice = number_format($oldPrice, 2, '.', '');
            $formattedNewPrice = number_format($newPrice, 2, '.', '');

            // IMPORTANT: $service->price in Paymenter must represent the pure recurring base price
            // (Plan + Config Options - Coupon). The CustomFees extension dynamically attaches fee line
            // items when invoices are generated via the InvoiceItemCreated event listener.
            // Do NOT add fees to $service->price here, as that causes fees to be charged twice on renewals.
            if ($formattedOldPrice !== $formattedNewPrice) {
                $service->update(['price' => $formattedNewPrice]);
                $currencyCode = $service->currency_code ?? ($service->currency->code ?? '');
                $updated[] = ($service->product->name ?? 'Service') . ' #' . $service->id . ': ' . $formattedOldPrice . ' -> ' . $formattedNewPrice . ' ' . $currencyCode;
            } else {
                $unchanged++;
            }
        }

        $this->recentLogs = $updated;

        $body = count($updated) > 0
            ? implode("\n", array_slice($updated, 0, 20))
            : 'No price changes detected.';

        if (count($updated) > 20) {
            $body .= "\n... and " . (count($updated) - 20) . ' more.';
        }

        Notification::make('recalculate_all_result')
            ->title('Processed ' . $services->count() . ' services')
            ->body(count($updated) . ' updated, ' . $unchanged . ' unchanged.' . (count($updated) > 0 ? "\n\n" . $body : ''))
            ->success()
            ->duration(15000)
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run_recalculation')
                ->label('Recalculate All Active Services')
                ->icon('ri-refresh-line')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Recalculate All Active Service Prices')
                ->modalDescription('This will recalculate recurring base prices for ALL active and suspended services using current product prices, plan prices, and config options. Services with price differences (such as previously baked-in fees) will be reset to the correct base price.')
                ->action(fn () => $this->recalculateAll()),
        ];
    }
}