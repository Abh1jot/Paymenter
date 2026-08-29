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

        if ($service->product->server) {
            if ($service->status == Service::STATUS_SUSPENDED) {
                UnsuspendJob::dispatch($service);
            } elseif ($service->status == Service::STATUS_PENDING) {
                CreateJob::dispatch($service);
            }
        }

        $service->expires_at = $service->calculateNextDueDate();
        $service->status = Service::STATUS_ACTIVE;
        $service->save();
    }
}
