<x-filament-panels::page>
    @php
        $stats = $this->getStats();
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="fi-ta-content bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-5 rounded-xl shadow-sm">
            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Linked Accounts</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {{ $stats['linked_users'] }}
            </div>
            <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-medium">
                {{ $stats['linked_percentage'] }}% of total users ({{ $stats['total_users'] }})
            </div>
        </div>

        <div class="fi-ta-content bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-5 rounded-xl shadow-sm">
            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Connected Guilds</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {{ $stats['guilds_count'] }}
            </div>
            <div class="text-xs text-gray-500 mt-1">
                Active server integrations
            </div>
        </div>

        <div class="fi-ta-content bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-5 rounded-xl shadow-sm">
            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Sync Success Rate</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {{ $stats['sync_health'] }}%
            </div>
            <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-medium">
                Health & API delivery score
            </div>
        </div>

        <div class="fi-ta-content bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-5 rounded-xl shadow-sm">
            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">DMs & Reminders Sent</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {{ $stats['notifications_sent'] }}
            </div>
            <div class="text-xs text-gray-500 mt-1">
                {{ $stats['notifications_failed'] }} failed (DMs disabled by user)
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-lg font-bold mb-3 text-gray-900 dark:text-white">Recent Synchronization Activity</h2>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
