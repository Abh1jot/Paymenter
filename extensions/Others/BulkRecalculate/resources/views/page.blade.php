<x-filament-panels::page>
    <div class="space-y-6">
        <div class="p-6 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">About Bulk Price Recalculation</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 leading-relaxed">
                When you update product base prices, modify configurable option costs, or adjust fee percentages, existing active services do not automatically change their recurring billing price. Use this tool to scan all active and suspended services and synchronize their recurring price with the latest pricing rules.
            </p>
            <div class="flex items-center gap-3">
                <x-filament::button
                    wire:click="recalculateAll"
                    wire:confirm="Are you sure you want to recalculate recurring prices for all active services?"
                    color="warning"
                    icon="ri-refresh-line"
                >
                    Recalculate All Service Prices Now
                </x-filament::button>
            </div>
        </div>

        @if (!empty($recentLogs))
            <div class="p-6 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
                <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Recently Updated Services ({{ count($recentLogs) }})</h4>
                <div class="max-h-64 overflow-y-auto space-y-1 font-mono text-xs text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-950 p-4 rounded-lg">
                    @foreach ($recentLogs as $log)
                        <div>{{ $log }}</div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
