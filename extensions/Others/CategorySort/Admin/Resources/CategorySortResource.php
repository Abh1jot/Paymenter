<?php

namespace Paymenter\Extensions\Others\CategorySort\Admin\Resources;

use App\Models\Category;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\CategorySort\Admin\Resources\CategorySortResource\Pages\ListCategorySort;

class CategorySortResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-drag-move-line';

    protected static ?string $navigationLabel = 'Category Order';

    protected static ?string $title = 'Category Order';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('info')
                ->label('')
                ->content('Use the drag handle on the list view to reorder categories. Changes are saved instantly.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort')
                    ->label('#')
                    ->width('4rem')
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->label('Category')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Top-level'),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products'),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->color('gray'),
            ])
            ->defaultSort('sort', 'asc')
            ->reorderable('sort')
            ->paginationPageOptions([25, 50, 100])
            ->recordActions([
                EditAction::make()->hidden(), // disable individual edit; only reorder matters
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategorySort::route('/'),
        ];
    }
}
