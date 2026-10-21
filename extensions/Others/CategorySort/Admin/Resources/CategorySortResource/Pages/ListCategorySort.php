<?php

namespace Paymenter\Extensions\Others\CategorySort\Admin\Resources\CategorySortResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\CategorySort\Admin\Resources\CategorySortResource;

class ListCategorySort extends ListRecords
{
    protected static string $resource = CategorySortResource::class;

    protected function getHeaderActions(): array
    {
        return []; // No create; categories are created via Paymenter's native admin
    }
}
