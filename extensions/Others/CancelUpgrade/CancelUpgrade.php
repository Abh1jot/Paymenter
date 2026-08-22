<?php

namespace Paymenter\Extensions\Others\CancelUpgrade;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceUpgrade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;

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

        // 2. Universal footer hook for service pages (works on ALL themes)
        // CRITICAL: Paymenter hook() / renderEvent() requires returning ['view' => '<html>']
        Event::listen('footer', function () {
            $path = request()->path();

            // Only run on service detail pages
            if (!str_starts_with($path, 'services/') && !str_contains($path, 'services')) {
                return null;
            }

            // Try to get service from route
            $service = request()->route('service');
            if ($service instanceof Service) {
                // Good, we have a Service model
            } elseif (is_numeric($service)) {
                $service = Service::find($service);
            } else {
                // Try to find service ID from URL path
                $parts = explode('/', trim($path, '/'));
                $servicesIdx = array_search('services', $parts);
                if ($servicesIdx !== false && isset($parts[$servicesIdx + 1]) && is_numeric($parts[$servicesIdx + 1])) {
                    $service = Service::find($parts[$servicesIdx + 1]);
                }
            }

            if (!$service) {
                return null;
            }

            // Check if this service belongs to the current user
            if (auth()->guest() || $service->user_id !== auth()->id()) {
                return null;
            }

            $pendingUpgrade = $service->upgrade()
                ->where('status', ServiceUpgrade::STATUS_PENDING)
                ->first();

            if (!$pendingUpgrade) {
                return null;
            }

            $unpaidInvoice = $pendingUpgrade->invoice && $pendingUpgrade->invoice->status === Invoice::STATUS_PENDING
                ? $pendingUpgrade->invoice
                : null;

            if (!$unpaidInvoice) {
                return null;
            }

            $invoiceUrl = route('invoices.show', $unpaidInvoice);
            $cancelUrl = route('services.cancel-upgrade', $service);
            $csrfToken = csrf_token();
            $invoiceId = $unpaidInvoice->id;

            return [
                'view' => '<script data-extension="cancel-upgrade">
(function() {
    function injectCancelBanner() {
        if (document.querySelector(".cancel-upgrade-banner")) return;

        var main = document.querySelector("main") || document.querySelector(".container") || document.querySelector("#app") || document.body;
        if (!main) return;

        var banner = document.createElement("div");
        banner.className = "cancel-upgrade-banner";
        banner.style.cssText = "padding:16px;margin:16px auto;max-width:80rem;border-radius:12px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;";
        banner.innerHTML = \'<div style="flex:1;min-width:200px"><h4 style="font-weight:600;font-size:14px;color:#fbbf24;margin:0">Pending Upgrade in Progress</h4><p style="font-size:12px;color:#fde68a;margin:4px 0 0;opacity:0.8">This service has an unpaid upgrade invoice #' . $invoiceId . '. Pay the invoice to complete the upgrade, or cancel it below.</p></div><div style="display:flex;gap:8px;flex-shrink:0"><a href="' . $invoiceUrl . '" style="padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;background:#f59e0b;color:#000;text-decoration:none">Pay Invoice</a><form action="' . $cancelUrl . '" method="POST" onsubmit="return confirm(\\\'Cancel this pending upgrade and its invoice?\\\');" style="display:inline"><input type="hidden" name="_token" value="' . $csrfToken . '"><button type="submit" style="padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;background:rgba(239,68,68,0.2);color:#fca5a5;border:1px solid rgba(239,68,68,0.3);cursor:pointer">Cancel Upgrade</button></form></div>\';

        // Insert at top of main content
        var firstChild = main.querySelector(".container, .mt-14, .mt-16, [class*=container]");
        if (firstChild) {
            firstChild.parentElement.insertBefore(banner, firstChild);
        } else {
            main.insertBefore(banner, main.firstChild);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", injectCancelBanner);
    } else {
        setTimeout(injectCancelBanner, 100);
    }
})();
</script>',
            ];
        });
    }
}
