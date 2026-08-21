<?php

namespace Paymenter\Extensions\Others\HideCategories\Admin\Resources\CategoryVisibilityResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\HideCategories\Admin\Resources\CategoryVisibilityResource;

class ListCategoryVisibility extends ListRecords
{
    protected static string $resource = CategoryVisibilityResource::class;
}
