<?php

namespace Paymenter\Extensions\Others\CancelUpgrade\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceUpgrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CancelUpgradeController extends Controller
{
    /**
     * Cancel a pending service upgrade.
     *
     * Deletes the pending ServiceUpgrade record and cancels its associated
     * unpaid invoice, freeing the service so the user can upgrade again.
     */
    public function cancel(Request $request, Service $service)
    {
        // Find the pending upgrade for this service
        $pendingUpgrade = $service->upgrade()
            ->where('status', ServiceUpgrade::STATUS_PENDING)
            ->first();

        if (! $pendingUpgrade) {
            return redirect()->route('services.show', $service)
                ->with('error', 'No pending upgrade found.');
        }

        // Only allow cancellation if the invoice is unpaid
        $invoice = $pendingUpgrade->invoice;
        if ($invoice && $invoice->status === Invoice::STATUS_PAID) {
            return redirect()->route('services.show', $service)
                ->with('error', 'Cannot cancel an upgrade that has already been paid.');
        }

        DB::transaction(function () use ($pendingUpgrade, $invoice) {
            // Cancel the associated invoice (if it exists and is pending)
            if ($invoice && $invoice->status === Invoice::STATUS_PENDING) {
                $invoice->status = Invoice::STATUS_CANCELLED;
                $invoice->save();
            }

            // Delete the upgrade's config options
            $pendingUpgrade->configs()->delete();

            // Delete the upgrade record itself
            $pendingUpgrade->delete();
        });

        return redirect()->route('services.show', $service)
            ->with('success', 'Upgrade cancelled successfully. You can now initiate an upgrade again.');
    }
}
