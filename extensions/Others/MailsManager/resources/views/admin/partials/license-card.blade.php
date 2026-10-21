@php
    $license = $this->license;
    $isValid = $license['is_valid'] ?? false;
    $isDev = $license['is_dev'] ?? false;
@endphp

<div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm overflow-hidden mb-6">
    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-200 dark:border-white/10 flex items-center justify-between flex-wrap gap-3 bg-gray-50/50 dark:bg-white/[0.02]">
        <div class="flex items-center gap-2.5">
            <span class="p-2 rounded-lg bg-primary-500/10 text-primary-600 dark:text-primary-400">
                <x-ri-shield-check-line class="w-5 h-5" />
            </span>
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    BuiltByBit License Verification
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Official Marketplace Authenticity & Integrity Tracking</p>
            </div>
        </div>

        <div>
            @if ($isDev)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                    <span class="w-2 h-2 rounded-full bg-purple-500 animate-pulse"></span>
                    Developer Mode
                </span>
            @elseif ($isValid)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Active & Verified
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Unverified
                </span>
            @endif
        </div>
    </div>

    {{-- Content --}}
    <div class="p-5 space-y-4">
        {{-- Metadata grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-3 rounded-lg bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Registered Buyer</span>
                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200 break-words">
                    {{ $license['buyer_username'] }} <span class="text-xs text-gray-400 font-mono">(ID: {{ $license['buyer_id'] }})</span>
                </span>
            </div>

            <div class="p-3 rounded-lg bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Build Tracking Nonce</span>
                <span class="text-xs font-mono font-semibold text-primary-600 dark:text-primary-400 break-all">
                    {{ $license['download_nonce'] }}
                </span>
            </div>

            <div class="p-3 rounded-lg bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Build Date & Resource</span>
                <span class="text-xs font-medium text-gray-700 dark:text-gray-300 block">
                    {{ $license['download_timestamp'] }}
                </span>
                <span class="text-[10px] text-gray-400 uppercase font-mono">Resource #{{ $license['resource_id'] }}</span>
            </div>
        </div>

        @if (!$isDev && !$isValid)
            <div class="rounded-lg p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2">
                <x-ri-information-line class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                <span>
                    <strong>Verification Required:</strong> Please enter your BuiltByBit Order Transaction ID below to authorize this copy of MailsManager.
                </span>
            </div>
        @endif

        {{-- License input action --}}
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-1">
            <div class="flex-1 relative">
                <input
                    type="text"
                    wire:model="licenseKeyInput"
                    placeholder="{{ !empty($license['license_key']) ? 'Active Key: ' . substr($license['license_key'], 0, 8) . '...' : 'Enter BuiltByBit Order ID / License Key (e.g. BBB-12345)' }}"
                    class="w-full text-xs font-mono rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 px-3.5 py-2"
                />
            </div>
            <button
                type="button"
                wire:click="verifyLicense"
                wire:loading.attr="disabled"
                class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 active:bg-primary-700 disabled:opacity-50 transition-colors shadow-sm"
            >
                <span wire:loading.remove wire:target="verifyLicense">
                    <x-ri-key-line class="w-4 h-4 inline" />
                    Verify License
                </span>
                <span wire:loading wire:target="verifyLicense">
                    <x-ri-loader-4-line class="w-4 h-4 inline animate-spin" />
                    Verifying...
                </span>
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    // Self-executing IIFE for MailsManager BuiltByBit licensing widget
    if (window._bbb_mailsmanager_init) return;
    window._bbb_mailsmanager_init = true;
})();
</script>