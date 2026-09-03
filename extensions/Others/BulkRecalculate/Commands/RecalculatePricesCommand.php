<?php

namespace Paymenter\Extensions\Others\BulkRecalculate\Commands;

use App\Models\Service;
use Illuminate\Console\Command;

class RecalculatePricesCommand extends Command
{
    protected $signature = 'services:recalculate-prices';

    protected $description = 'Recalculate base recurring prices for all active and suspended services, removing double-baked fees';

    public function handle()
    {
        $this->info('Starting bulk price recalculation for all active and suspended services...');

        $services = Service::whereIn('status', ['active', 'suspended'])
            ->with(['product', 'plan.prices', 'configs.configValue', 'coupon', 'currency'])
            ->get();

        $updatedCount = 0;
        $unchangedCount = 0;

        foreach ($services as $service) {
            $oldPrice = (float) $service->price;
            $newPrice = (float) $service->calculatePrice();

            $formattedOldPrice = number_format($oldPrice, 2, '.', '');
            $formattedNewPrice = number_format($newPrice, 2, '.', '');

            if ($formattedOldPrice !== $formattedNewPrice) {
                $service->update(['price' => $formattedNewPrice]);
                $currencyCode = $service->currency_code ?? ($service->currency->code ?? '');
                $this->line(sprintf(
                    '  [UPDATED] Service #%d (%s): %s -> %s %s',
                    $service->id,
                    $service->product->name ?? 'Product',
                    $formattedOldPrice,
                    $formattedNewPrice,
                    $currencyCode
                ));
                $updatedCount++;
            } else {
                $unchangedCount++;
            }
        }

        $this->info(sprintf(
            'Recalculation completed! Processed %d services: %d updated, %d unchanged.',
            $services->count(),
            $updatedCount,
            $unchangedCount
        ));

        return Command::SUCCESS;
    }
}