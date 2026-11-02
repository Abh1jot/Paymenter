<?php

namespace App\Services\Service;

use App\Jobs\Server\CreateJob;
use App\Jobs\Server\UnsuspendJob;
use App\Models\Service;

class RenewServiceService
{
    /**
     * Handle the service renewal.
     *
     * @return void
     */
    public function handle(Service $service)
    {
        // Defense-in-depth guard: if this service is already active with a future expiry,
        // do not renew it again. This prevents double-renewal if any code path (e.g. an extension
        // creating multiple invoice items that all reference the same Service) calls this method
        // more than once for the same service during a single payment transaction.
        if ($service->status === Service::STATUS_ACTIVE
            && $service->expires_at
            && $service->expires_at->isFuture()) {
            return;
        }

        $wasPending = ($service->status == Service::STATUS_PENDING);

        $service->expires_at = $service->calculateNextDueDate();
        $service->status = Service::STATUS_ACTIVE;
        $service->save();

        if ($service->product?->server) {
            if ($wasPending) {
                CreateJob::dispatch($service);
            } else {
                // Immediate synchronous unsuspend to restore service right away for the user
                try {
                    \App\Helpers\ExtensionHelper::unsuspendServer($service);
                } catch (\Throwable $e) {
                    report($e);
                    UnsuspendJob::dispatch($service);
                }
            }
        }
    }
}
