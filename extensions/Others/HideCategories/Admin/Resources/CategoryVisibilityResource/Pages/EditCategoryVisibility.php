<?php

namespace Paymenter\Extensions\Others\HideCategories\Admin\Resources\CategoryVisibilityResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Others\HideCategories\Admin\Resources\CategoryVisibilityResource;

class EditCategoryVisibility extends EditRecord
{
    protected static string $resource = CategoryVisibilityResource::class;
}
