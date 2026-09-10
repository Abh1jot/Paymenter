<x-filament-panels::page>
    @php
        $data = $this->getAnalyticsData();
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-6 rounded-xl shadow-sm">
            <div class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Account Linking Growth</div>
            <div class="text-3xl font-extrabold text-primary mt-2">{{ $data['total_linked'] }}</div>
            <div class="text-xs text-emerald-600 font-medium mt-1">+{{ $data['linked_this_month'] }} new linkings this month</div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-6 rounded-xl shadow-sm">
            <div class="text-xs text-gray-500 font-semibold uppercase tracking-wider">DM Delivery Success</div>
            <div class="text-3xl font-extrabold text-emerald-500 mt-2">{{ $data['delivery_rate'] }}%</div>
            <div class="text-xs text-gray-500 mt-1">{{ $data['dms_sent'] }} delivered, {{ $data['dms_failed'] }} bounced</div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-6 rounded-xl shadow-sm">
            <div class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Automated Reminders</div>
            <div class="text-3xl font-extrabold text-amber-500 mt-2">{{ $data['reminders_sent'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Pre-expiration renewal notices delivered</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-6 rounded-xl shadow-sm">
            <h3 class="font-bold text-base text-gray-900 dark:text-white mb-4">Role Synchronization Velocity</h3>
            <div class="space-y-4 text-sm">
                <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-800">
                    <span class="text-gray-600 dark:text-gray-400">Total Sync Events Executed</span>
                    <span class="font-bold text-gray-900 dark:text-white">{{ $data['total_sync_actions'] }}</span>
                </div>
                <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-800">
                    <span class="text-gray-600 dark:text-gray-400">Role Additions Processed</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400">+{{ $data['roles_added'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 dark:text-gray-400">Role Revocations (Expired/Suspended)</span>
                    <span class="font-bold text-red-500">-{{ $data['roles_removed'] }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-6 rounded-xl shadow-sm">
            <h3 class="font-bold text-base text-gray-900 dark:text-white mb-4">Engagement Summary</h3>
            <div class="space-y-4 text-sm">
                <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-800">
                    <span class="text-gray-600 dark:text-gray-400">Customer Slash Command Usage</span>
                    <span class="font-bold text-gray-900 dark:text-white">Active via HTTP Webhooks</span>
                </div>
                <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-800">
                    <span class="text-gray-600 dark:text-gray-400">Discord Linked Roles Sync</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400">Enabled</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 dark:text-gray-400">Auto-Join Server Rate</span>
                    <span class="font-bold text-primary">100% on OAuth</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
