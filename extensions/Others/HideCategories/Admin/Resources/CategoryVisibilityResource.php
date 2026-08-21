<?php

namespace Paymenter\Extensions\Others\HideCategories\Admin\Resources;

use App\Models\Category;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\HideCategories\Admin\Resources\CategoryVisibilityResource\Pages\EditCategoryVisibility;
use Paymenter\Extensions\Others\HideCategories\Admin\Resources\CategoryVisibilityResource\Pages\ListCategoryVisibility;

class CategoryVisibilityResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-eye-off-line';

    protected static ?string $navigationLabel = 'Category Visibility';

    protected static ?string $title = 'Category Visibility';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->disabled()
                    ->label('Category Name'),
                TextInput::make('slug')
                    ->disabled()
                    ->label('Slug'),
                Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->disabled()
                    ->label('Parent Category'),
                Toggle::make('hidden')
                    ->label('Hide from Public Storefront')
                    ->helperText('When enabled, this category and its products will be hidden from the public store and navigation.')
                    ->default(false)
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('None (Top-level)')
                    ->sortable(),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products')
                    ->sortable(),
                ToggleColumn::make('hidden')
                    ->label('Hidden')
                    ->onColor('danger')
                    ->offColor('success')
                    ->onIcon('ri-eye-off-line')
                    ->offIcon('ri-eye-line'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('sort', 'asc')
            ->reorderable('sort');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategoryVisibility::route('/'),
            'edit' => EditCategoryVisibility::route('/{record}/edit'),
        ];
    }
}
