<?php

namespace Paymenter\Extensions\Others\BulkRecalculate;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\BulkRecalculate\Admin\Pages\RecalculatePricesPage;
use Paymenter\Extensions\Others\BulkRecalculate\Commands\RecalculatePricesCommand;

#[ExtensionMeta(
    name: 'Bulk Recalculate Prices',
    description: 'Recalculates recurring billing prices in bulk across all active and suspended services when changing product prices or fees.',
    version: '1.0.1',
    author: 'Azion Cloud',
    icon: 'ri-refresh-line'
)]
class BulkRecalculate extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString('Access the bulk price recalculation tool via <a class="text-primary-600 font-semibold" href="' . RecalculatePricesPage::getUrl() . '">Bulk Recalculate Prices</a> in the Administration menu, or run <code>php artisan services:recalculate-prices</code> on CLI.'),
            ],
        ];
    }

    public function boot()
    {
        View::addNamespace('bulk-recalculate', __DIR__ . '/resources/views');

        if (app()->runningInConsole()) {
            $this->commands([
                RecalculatePricesCommand::class,
            ]);
        }

        Event::listen('permissions', function () {
            return [
                'admin.bulk_recalculate.view' => 'Access Bulk Price Recalculation',
            ];
        });
    }
}