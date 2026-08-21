<?php

namespace Paymenter\Extensions\Others\CancelUpgrade;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;

#[ExtensionMeta(
    name: 'Cancel Upgrade',
    description: 'Allows clients to cancel pending unpaid service upgrades and their associated invoices on the service page.',
    version: '1.0.0',
    author: 'Azion Cloud',
    icon: 'ri-close-circle-line'
)]
class CancelUpgrade extends Extension
{
    public function boot()
    {
        // Register web routes
        require __DIR__ . '/routes/web.php';
        View::addNamespace('cancel-upgrade', __DIR__ . '/resources/views');

        // Hook into service detail pages
        Event::listen('pages.services.show', function ($data) {
            $service = $data['service'] ?? null;
            if (!$service) {
                return;
            }

            return [
                'view' => view('cancel-upgrade::button', ['service' => $service]),
            ];
        });
    }
}
