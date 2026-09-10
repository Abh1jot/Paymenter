<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Clusters\DiscordSuiteCluster;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\MassRoleSyncJob;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncUserRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordGuild;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordNotification;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordSyncLog;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;

class DiscordOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = DiscordSuiteCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-dashboard-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-dashboard-fill';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?int $navigationSort = 1;

    protected string $view = 'discord_suite::admin.overview';

    public function getHeaderActions(): array
    {
        return [
            Action::make('force_mass_sync')
                ->label('Force Mass Role Sync')
                ->icon('ri-refresh-line')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Trigger Mass Role Synchronization?')
                ->modalDescription('This will queue role calculations and Discord updates for all linked accounts.')
                ->action(function () {
                    MassRoleSyncJob::dispatch();
                    Notification::make()
                        ->title('Mass Role Sync Queued')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getStats(): array
    {
        $totalUsers = User::count();
        $linkedUsers = LinkedDiscordAccount::count();
        $guildsCount = DiscordGuild::count();
        $totalSyncs = DiscordSyncLog::count();
        $failedSyncs = DiscordSyncLog::where('status', 'failed')->count();
        $syncHealth = $totalSyncs > 0 ? round((($totalSyncs - $failedSyncs) / $totalSyncs) * 100, 1) : 100;
        $notificationsSent = DiscordNotification::where('status', 'sent')->count();
        $notificationsFailed = DiscordNotification::where('status', '!=', 'sent')->count();

        return [
            'total_users' => $totalUsers,
            'linked_users' => $linkedUsers,
            'linked_percentage' => $totalUsers > 0 ? round(($linkedUsers / $totalUsers) * 100, 1) : 0,
            'guilds_count' => $guildsCount,
            'sync_health' => $syncHealth,
            'notifications_sent' => $notificationsSent,
            'notifications_failed' => $notificationsFailed,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DiscordSyncLog::query()->latest()->limit(50))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M d, H:i:s')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->default('N/A'),
                TextColumn::make('discord_user_id')
                    ->label('Discord ID')
                    ->copyable()
                    ->fontFamily('mono')
                    ->size('xs'),
                TextColumn::make('guild_id')
                    ->label('Guild')
                    ->size('xs'),
                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'roles_added' => 'success',
                        'roles_removed' => 'warning',
                        'roles_added_and_removed' => 'info',
                        'no_change' => 'gray',
                        'error' => 'danger',
                        default => 'primary',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'retrying' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('details')
                    ->label('Details')
                    ->limit(40),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('retry')
                    ->label('Retry')
                    ->icon('ri-refresh-line')
                    ->color('warning')
                    ->visible(fn (DiscordSyncLog $record) => $record->status === 'failed' && $record->user_id !== null)
                    ->action(function (DiscordSyncLog $record) {
                        SyncUserRolesJob::dispatch($record->user_id);
                        Notification::make()->title('Sync Queued')->success()->send();
                    }),
            ]);
    }
}
