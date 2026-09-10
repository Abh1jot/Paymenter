<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Pages;

use Filament\Pages\Page;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Clusters\DiscordSuiteCluster;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordNotification;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordSyncLog;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;

class Analytics extends Page
{
    protected static ?string $cluster = DiscordSuiteCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-bar-chart-box-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-bar-chart-box-fill';

    protected static ?string $navigationLabel = 'Analytics & Insights';

    protected static ?int $navigationSort = 5;

    protected string $view = 'discord_suite::admin.analytics';

    public function getAnalyticsData(): array
    {
        $totalLinked = LinkedDiscordAccount::count();
        $linkedThisMonth = LinkedDiscordAccount::where('created_at', '>=', now()->startOfMonth())->count();

        $totalSyncActions = DiscordSyncLog::count();
        $rolesAdded = DiscordSyncLog::whereIn('action', ['roles_added', 'roles_added_and_removed'])->count();
        $rolesRemoved = DiscordSyncLog::whereIn('action', ['roles_removed', 'roles_added_and_removed'])->count();

        $dmsSent = DiscordNotification::where('status', 'sent')->count();
        $dmsFailed = DiscordNotification::where('status', '!=', 'sent')->count();
        $dmDeliveryRate = ($dmsSent + $dmsFailed) > 0 ? round(($dmsSent / ($dmsSent + $dmsFailed)) * 100, 1) : 100;

        $remindersSent = DiscordNotification::where('type', 'LIKE', 'reminder_%')->where('status', 'sent')->count();

        return [
            'total_linked' => $totalLinked,
            'linked_this_month' => $linkedThisMonth,
            'total_sync_actions' => $totalSyncActions,
            'roles_added' => $rolesAdded,
            'roles_removed' => $rolesRemoved,
            'dms_sent' => $dmsSent,
            'dms_failed' => $dmsFailed,
            'delivery_rate' => $dmDeliveryRate,
            'reminders_sent' => $remindersSent,
        ];
    }
}
