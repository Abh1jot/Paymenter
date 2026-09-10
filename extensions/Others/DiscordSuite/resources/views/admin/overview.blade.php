<x-filament-panels::page>
    @php
        $stats = $this->getStats();
    @endphp

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <x-filament::section>
            <div style="font-size: 0.75rem; color: #9ca3af; font-weight: 500;">Linked Accounts</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #f3f4f6; margin-top: 0.25rem;">
                {{ $stats['linked_users'] }}
            </div>
            <div style="font-size: 0.75rem; color: #34d399; margin-top: 0.25rem; font-weight: 500;">
                {{ $stats['linked_percentage'] }}% of total users ({{ $stats['total_users'] }})
            </div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size: 0.75rem; color: #9ca3af; font-weight: 500;">Connected Guilds</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #f3f4f6; margin-top: 0.25rem;">
                {{ $stats['guilds_count'] }}
            </div>
            <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;">
                Active server integrations
            </div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size: 0.75rem; color: #9ca3af; font-weight: 500;">Sync Success Rate</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #f3f4f6; margin-top: 0.25rem;">
                {{ $stats['sync_health'] }}%
            </div>
            <div style="font-size: 0.75rem; color: #34d399; margin-top: 0.25rem; font-weight: 500;">
                Health & API delivery score
            </div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size: 0.75rem; color: #9ca3af; font-weight: 500;">DMs & Reminders Sent</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #f3f4f6; margin-top: 0.25rem;">
                {{ $stats['notifications_sent'] }}
            </div>
            <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;">
                {{ $stats['notifications_failed'] }} failed (DMs disabled by user)
            </div>
        </x-filament::section>
    </div>

    <div>
        <h2 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.75rem; color: #f3f4f6;">Recent Synchronization Activity</h2>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
