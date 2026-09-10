<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Clusters\DiscordSuiteCluster;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\LinkedAccountResource\Pages\ListLinkedAccounts;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncUserRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordNotificationService;

class LinkedAccountResource extends Resource
{
    protected static ?string $model = LinkedDiscordAccount::class;

    protected static ?string $cluster = DiscordSuiteCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-user-shared-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-user-shared-fill';

    protected static ?string $navigationLabel = 'Linked Accounts';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->circular(),

                TextColumn::make('discord_username')
                    ->label('Discord User')
                    ->description(fn (LinkedDiscordAccount $record) => $record->formatted_tag)
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('discord_user_id')
                    ->label('Discord ID')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Paymenter Client')
                    ->description(fn (LinkedDiscordAccount $record) => $record->user?->email)
                    ->searchable(),

                TextColumn::make('last_synced_at')
                    ->label('Last Role Sync')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Linked On')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('sync_roles')
                    ->label('Sync Roles')
                    ->icon('ri-refresh-line')
                    ->color('primary')
                    ->action(function (LinkedDiscordAccount $record) {
                        SyncUserRolesJob::dispatch($record->user_id);
                        Notification::make()->title('Role Sync Dispatched')->success()->send();
                    }),

                Action::make('test_dm')
                    ->label('Test DM')
                    ->icon('ri-chat-1-line')
                    ->color('gray')
                    ->action(function (LinkedDiscordAccount $record, DiscordNotificationService $notificationService) {
                        if ($record->user) {
                            $embed = [
                                'title' => '🔔 Test Notification from Paymenter',
                                'description' => 'Your Discord account integration is active and working properly!',
                                'color' => 0x5865F2,
                            ];
                            $sent = $notificationService->sendDM($record->user, 'test_notification', $embed);
                            if ($sent) {
                                Notification::make()->title('Test DM Sent Successfully!')->success()->send();
                            } else {
                                Notification::make()->title('Failed to Send DM')->body('Ensure customer allows DMs from server members.')->danger()->send();
                            }
                        }
                    }),

                DeleteAction::make()
                    ->label('Unlink'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLinkedAccounts::route('/'),
        ];
    }
}
