<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Resources;

use App\Models\Category;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Clusters\DiscordSuiteCluster;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\RoleMappingResource\Pages\CreateRoleMapping;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\RoleMappingResource\Pages\EditRoleMapping;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\RoleMappingResource\Pages\ListRoleMappings;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordGuild;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordRoleMapping;

class RoleMappingResource extends Resource
{
    protected static ?string $model = DiscordRoleMapping::class;

    protected static ?string $cluster = DiscordSuiteCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-shield-user-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-shield-user-fill';

    protected static ?string $navigationLabel = 'Role Mappings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Select::make('guild_id')
                        ->label('Discord Server')
                        ->options(function () {
                            $guilds = DiscordGuild::all()->pluck('name', 'guild_id')->toArray();
                            return !empty($guilds) ? $guilds : ['default' => 'Primary Server'];
                        })
                        ->default(fn () => DiscordGuild::first()?->guild_id ?? 'default')
                        ->searchable()
                        ->required(),

                    Select::make('rule_type')
                        ->label('Rule Type')
                        ->options([
                            'customer_tier' => 'Customer Verification Tier (Active, Verified, VIP)',
                            'product' => 'Specific Product Purchase',
                            'category' => 'Product Category Ownership',
                        ])
                        ->default('customer_tier')
                        ->live()
                        ->required(),

                    Select::make('tier_type')
                        ->label('Customer Tier Condition')
                        ->options([
                            'active' => 'Active Customer (Has >= 1 active service)',
                            'verified' => 'Verified Customer (Email verified or paid invoice)',
                            'premium' => 'Premium VIP Customer (Min spend threshold)',
                            'vps' => 'VPS / Cloud Customer',
                            'dedicated' => 'Dedicated Server Customer',
                            'minecraft' => 'Minecraft Server Customer',
                        ])
                        ->visible(fn (Get $get) => $get('rule_type') === 'customer_tier')
                        ->required(fn (Get $get) => $get('rule_type') === 'customer_tier'),

                    TextInput::make('min_spend')
                        ->label('Minimum Total Spent ($)')
                        ->numeric()
                        ->placeholder('100.00')
                        ->visible(fn (Get $get) => $get('rule_type') === 'customer_tier' && $get('tier_type') === 'premium')
                        ->required(fn (Get $get) => $get('rule_type') === 'customer_tier' && $get('tier_type') === 'premium'),

                    Select::make('target_id')
                        ->label(fn (Get $get) => $get('rule_type') === 'product' ? 'Product' : 'Category')
                        ->options(function (Get $get) {
                            if ($get('rule_type') === 'product') {
                                return Product::all()->pluck('name', 'id')->toArray();
                            }
                            if ($get('rule_type') === 'category') {
                                return Category::all()->pluck('name', 'id')->toArray();
                            }
                            return [];
                        })
                        ->searchable()
                        ->visible(fn (Get $get) => in_array($get('rule_type'), ['product', 'category']))
                        ->required(fn (Get $get) => in_array($get('rule_type'), ['product', 'category'])),

                    TextInput::make('discord_role_name')
                        ->label('Discord Role Label')
                        ->placeholder('e.g. @Active Customer')
                        ->required(),

                    TextInput::make('discord_role_id')
                        ->label('Discord Role Snowflake ID')
                        ->placeholder('e.g. 109283746501928374')
                        ->helperText('Right click the role in Discord (Developer Mode enabled) and click Copy ID')
                        ->required(),

                    TextInput::make('priority')
                        ->label('Rule Priority')
                        ->numeric()
                        ->default(10)
                        ->helperText('Higher priority rules are evaluated first'),

                    Toggle::make('enabled')
                        ->label('Rule Enabled')
                        ->default(true)
                        ->inline(false),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('discord_role_name')
                    ->label('Discord Role')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('discord_role_id')
                    ->label('Role ID')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->copyable(),

                TextColumn::make('rule_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'customer_tier' => 'info',
                        'product' => 'success',
                        'category' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('target_name')
                    ->label('Target Condition'),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable(),

                IconColumn::make('enabled')
                    ->label('Active')
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
            'index' => ListRoleMappings::route('/'),
            'create' => CreateRoleMapping::route('/create'),
            'edit' => EditRoleMapping::route('/{record}/edit'),
        ];
    }
}
