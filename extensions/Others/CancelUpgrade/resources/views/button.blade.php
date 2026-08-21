@php
    $pendingUpgrade = isset($service) ? $service->upgrade()->where('status', \App\Models\ServiceUpgrade::STATUS_PENDING)->first() : null;
    $unpaidInvoice = $pendingUpgrade?->invoice && $pendingUpgrade->invoice->status === \App\Models\Invoice::STATUS_PENDING ? $pendingUpgrade->invoice : null;
@endphp

@if ($pendingUpgrade && $unpaidInvoice)
    <div class="p-4 mb-6 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h4 class="font-semibold text-sm text-amber-300">Pending Upgrade in Progress</h4>
            <p class="text-xs text-amber-200/80 mt-0.5">This service has an unpaid upgrade invoice #{{ $unpaidInvoice->id }}. Pay the invoice to complete the upgrade, or cancel it below to unlock the service.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('invoices.show', $unpaidInvoice) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-500 text-black hover:bg-amber-400 transition-colors">
                Pay Invoice
            </a>
            <form action="{{ route('services.cancel-upgrade', $service) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this pending upgrade and its invoice?');">
                @csrf
                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-500/20 text-red-300 border border-red-500/30 hover:bg-red-500/30 transition-colors">
                    Cancel Upgrade
                </button>
            </form>
        </div>
    </div>
@endif
