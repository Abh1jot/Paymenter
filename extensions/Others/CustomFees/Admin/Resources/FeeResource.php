<?php

namespace Paymenter\Extensions\Others\CustomFees\Admin\Resources;

use App\Models\Category;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource\Pages\CreateFee;
use Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource\Pages\EditFee;
use Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource\Pages\ListFees;
use Paymenter\Extensions\Others\CustomFees\Models\Fee;

class FeeResource extends Resource
{
    protected static ?string $model = Fee::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-percent-line';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Fee Name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., GST, Processing Fee, Service Fee'),
                TextInput::make('rate')
                    ->label('Rate')
                    ->mask(RawJs::make(
                        <<<'JS'
                            $money($input, '.', '', 4)
                        JS
                    ))
                    ->required()
                    ->suffix('%')
                    ->placeholder('e.g., 18.00'),
                Toggle::make('enabled')
                    ->label('Enabled')
                    ->default(true)
                    ->helperText('Disable to temporarily stop applying this fee.'),
                Select::make('products')
                    ->label('Apply to Products')
                    ->relationship('products', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Select specific products this fee applies to. Leave empty if applying to categories only.'),
                Select::make('categories')
                    ->label('Apply to Categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Select categories — fee applies to ALL products under these categories.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rate')
                    ->label('Rate')
                    ->suffix('%')
                    ->sortable(),
                IconColumn::make('enabled')
                    ->boolean()
                    ->label('Enabled'),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products'),
                TextColumn::make('categories_count')
                    ->counts('categories')
                    ->label('Categories'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFees::route('/'),
            'create' => CreateFee::route('/create'),
            'edit' => EditFee::route('/{record}/edit'),
        ];
    }
}
