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

            // Include custom fees in the recalculated price so the stored service
            // price matches what will actually appear on the next renewal invoice.
            // Without this, BulkRecalculate would strip fees from service->price
            // because calculatePrice() has no knowledge of the CustomFees extension.
            try {
                if (class_exists(\Paymenter\Extensions\Others\CustomFees\Models\Fee::class)) {
                    $fees = \Paymenter\Extensions\Others\CustomFees\Models\Fee::forProduct($service->product);
                    foreach ($fees as $fee) {
                        $newPrice += (float) $fee->calculateFee($newPrice);
                    }
                    $newPrice = number_format($newPrice, 2, '.', '');
                }
            } catch (\Exception $e) {
                // CustomFees not available or failed, continue with base price
            }

            if (number_format($oldPrice, 2, '.', '') !== number_format($newPrice, 2, '.', '')) {
                $service->update(['price' => $newPrice]);
                $updated[] = ($service->product->name ?? 'Service') . ' #' . $service->id . ': ' . number_format($oldPrice, 2) . ' ? ' . number_format($newPrice, 2) . ' ' . ($service->currency ?? '');
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
                ->modalDescription('This will recalculate recurring prices for ALL active and suspended services using current product prices, config options, and fees. Services with price differences will be updated automatically.')
                ->action(fn () => $this->recalculateAll()),
        ];
    }
}
