<?php

namespace Paymenter\Extensions\Others\HideCategories;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Category;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\HideCategories\Admin\Resources\CategoryVisibilityResource;

#[ExtensionMeta(
    name: 'Hide Categories',
    description: 'Allows administrators to hide specific categories from the storefront and customer navigation.',
    version: '1.0.0',
    author: 'Azion Cloud',
    icon: 'ri-eye-off-line'
)]
class HideCategories extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString('Manage category visibility via <a class="text-primary-600 font-semibold" href="' . CategoryVisibilityResource::getUrl() . '">Category Visibility</a> in the Administration menu.'),
            ],
        ];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/HideCategories/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/HideCategories/database/migrations');
    }

    public function boot()
    {
        // Automatically hide hidden categories on public storefront requests
        if (!request()->is('admin*')) {
            try {
                if (Schema::hasColumn('categories', 'hidden')) {
                    Category::addGlobalScope('visible', function ($builder) {
                        $builder->where('categories.hidden', false);
                    });
                }
            } catch (\Exception $e) {
                // Table or column not ready yet
            }
        }

        Event::listen('permissions', function () {
            return [
                'admin.category_visibility.view' => 'Manage Category Visibility',
            ];
        });
    }
}
