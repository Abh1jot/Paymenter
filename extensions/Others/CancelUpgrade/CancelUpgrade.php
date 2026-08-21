<?php

namespace Paymenter\Extensions\Others\CancelUpgrade;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceUpgrade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

#[ExtensionMeta(
    name: 'Cancel Upgrade',
    description: 'Allows clients to cancel pending unpaid service upgrades and their associated invoices directly on the service page across all themes.',
    version: '1.0.0',
    author: 'Azion Cloud',
    icon: 'ri-close-circle-line'
)]
class CancelUpgrade extends Extension
{
    public function boot()
    {
        // 1. Register web routes
        require __DIR__ . '/routes/web.php';
        View::addNamespace('cancel-upgrade', __DIR__ . '/resources/views');

        // 2. Hook into service detail pages (Standard Paymenter theme hook)
        Event::listen('pages.services.show', function ($data) {
            $service = $data['service'] ?? null;
            if (!$service instanceof Service) {
                return null;
            }

            return [
                'view' => view('cancel-upgrade::button', ['service' => $service]),
            ];
        });

        // 3. Universal fallback hook via footer (for themes that do not implement pages.services.show hook)
        Event::listen('footer', function () {
            if (!request()->routeIs('services.show') && !str_starts_with(request()->path(), 'services/')) {
                return null;
            }

            $service = request()->route('service');
            if (!$service instanceof Service && is_numeric($service)) {
                $service = Service::find($service);
            }

            if (!$service) {
                return null;
            }

            $pendingUpgrade = $service->upgrade()
                ->where('status', ServiceUpgrade::STATUS_PENDING)
                ->first();

            $unpaidInvoice = $pendingUpgrade?->invoice && $pendingUpgrade->invoice->status === Invoice::STATUS_PENDING
                ? $pendingUpgrade->invoice
                : null;

            if ($pendingUpgrade && $unpaidInvoice) {
                $invoiceUrl = route('invoices.show', $unpaidInvoice);
                $cancelUrl = route('services.cancel-upgrade', $service);
                $csrfToken = csrf_token();

                return new HtmlString(<<<HTML
<script data-extension="cancel-upgrade">
(function() {
    function injectCancelBanner() {
        if (document.querySelector('.cancel-upgrade-banner')) return;

        const mainContainer = document.querySelector('main') || document.querySelector('.container') || document.querySelector('#app') || document.body;
        if (!mainContainer) return;

        const banner = document.createElement('div');
        banner.className = 'cancel-upgrade-banner p-4 mb-6 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-lg max-w-7xl mx-auto my-4';
        banner.innerHTML = `
            <div>
                <h4 class="font-semibold text-sm text-amber-300">Pending Upgrade in Progress</h4>
                <p class="text-xs text-amber-200/80 mt-0.5">This service has an unpaid upgrade invoice #{$unpaidInvoice->id}. Pay the invoice to complete the upgrade, or cancel it below to unlock the service.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{$invoiceUrl}" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-500 text-black hover:bg-amber-400 transition-colors">
                    Pay Invoice
                </a>
                <form action="{$cancelUrl}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this pending upgrade and its invoice?');" style="display:inline;">
                    <input type="hidden" name="_token" value="{$csrfToken}">
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-500/20 text-red-300 border border-red-500/30 hover:bg-red-500/30 transition-colors">
                        Cancel Upgrade
                    </button>
                </form>
            </div>
        `;

        mainContainer.insertBefore(banner, mainContainer.firstChild);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectCancelBanner);
    } else {
        injectCancelBanner();
    }
})();
</script>
HTML);
            }

            return null;
        });
    }
}
