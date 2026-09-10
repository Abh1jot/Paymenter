<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Clusters\DiscordSuiteCluster;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\DiscordGuildResource\Pages\CreateDiscordGuild;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\DiscordGuildResource\Pages\EditDiscordGuild;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\DiscordGuildResource\Pages\ListDiscordGuilds;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordGuild;

class DiscordGuildResource extends Resource
{
    protected static ?string $model = DiscordGuild::class;

    protected static ?string $cluster = DiscordSuiteCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-community-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-community-fill';

    protected static ?string $navigationLabel = 'Discord Servers';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('Server Name')
                        ->placeholder('e.g. MyHosting Community')
                        ->required(),

                    TextInput::make('guild_id')
                        ->label('Server (Guild) Snowflake ID')
                        ->placeholder('e.g. 109283746501928374')
                        ->helperText('Right click your Discord server icon and click "Copy Server ID"')
                        ->required(),

                    TextInput::make('invite_url')
                        ->label('Public Server Invite URL')
                        ->placeholder('https://discord.gg/yourserver')
                        ->url(),

                    TextInput::make('welcome_channel_id')
                        ->label('Welcome Channel ID')
                        ->placeholder('e.g. 109283746501928374')
                        ->helperText('Channel ID to post join greetings'),

                    Textarea::make('welcome_message')
                        ->label('Custom Join Greeting Message')
                        ->placeholder('Welcome {user} to the server! Your customer roles have been synchronized.')
                        ->columnSpanFull(),

                    Toggle::make('auto_join_enabled')
                        ->label('Automatically Add Linked Users to This Server')
                        ->default(true)
                        ->helperText('Uses OAuth2 guilds.join scope to automatically add customers to this server upon linking'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Server Name')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('guild_id')
                    ->label('Guild ID')
                    ->fontFamily('mono')
                    ->copyable(),

                TextColumn::make('invite_url')
                    ->label('Invite')
                    ->limit(25),

                IconColumn::make('auto_join_enabled')
                    ->label('Auto-Join')
                    ->boolean(),

                IconColumn::make('bot_present')
                    ->label('Bot Verified')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => ListDiscordGuilds::route('/'),
            'create' => CreateDiscordGuild::route('/create'),
            'edit' => EditDiscordGuild::route('/{record}/edit'),
        ];
    }
}
