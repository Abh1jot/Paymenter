<x-filament-panels::page>
    @php
        $data = $this->getAnalyticsData();
    @endphp

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <x-filament::section>
            <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af;">Account Linking Growth</div>
            <div style="font-size: 2.25rem; font-weight: 800; color: #60a5fa; margin-top: 0.5rem; line-height: 1.2;">{{ $data['total_linked'] }}</div>
            <div style="font-size: 0.8125rem; font-weight: 500; color: #34d399; margin-top: 0.35rem;">+{{ $data['linked_this_month'] }} new linkings this month</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af;">DM Delivery Success</div>
            <div style="font-size: 2.25rem; font-weight: 800; color: #34d399; margin-top: 0.5rem; line-height: 1.2;">{{ $data['delivery_rate'] }}%</div>
            <div style="font-size: 0.8125rem; color: #9ca3af; margin-top: 0.35rem;">{{ $data['dms_sent'] }} delivered, {{ $data['dms_failed'] }} bounced</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af;">Automated Reminders</div>
            <div style="font-size: 2.25rem; font-weight: 800; color: #fbbf24; margin-top: 0.5rem; line-height: 1.2;">{{ $data['reminders_sent'] }}</div>
            <div style="font-size: 0.8125rem; color: #9ca3af; margin-top: 0.35rem;">Pre-expiration renewal notices delivered</div>
        </x-filament::section>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
        <x-filament::section>
            <x-slot name="heading">Role Synchronization Velocity</x-slot>
            <div style="display: flex; flex-direction: column; gap: 1rem; font-size: 0.875rem; margin-top: 0.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
                    <span style="color: #9ca3af;">Total Sync Events Executed</span>
                    <span style="font-weight: 700; color: #f3f4f6;">{{ $data['total_sync_actions'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
                    <span style="color: #9ca3af;">Role Additions Processed</span>
                    <span style="font-weight: 700; color: #34d399;">+{{ $data['roles_added'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #9ca3af;">Role Revocations (Expired/Suspended)</span>
                    <span style="font-weight: 700; color: #f87171;">-{{ $data['roles_removed'] }}</span>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Engagement Summary</x-slot>
            <div style="display: flex; flex-direction: column; gap: 1rem; font-size: 0.875rem; margin-top: 0.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
                    <span style="color: #9ca3af;">Customer Slash Command Usage</span>
                    <span style="font-weight: 700; color: #f3f4f6;">Active via HTTP Webhooks</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
                    <span style="color: #9ca3af;">Discord Linked Roles Sync</span>
                    <span style="font-weight: 700; color: #34d399;">Enabled</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #9ca3af;">Auto-Join Server Rate</span>
                    <span style="font-weight: 700; color: #60a5fa;">100% on OAuth</span>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
