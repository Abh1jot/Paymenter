<?php

namespace Paymenter\Extensions\Others\CustomFees;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource;

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
