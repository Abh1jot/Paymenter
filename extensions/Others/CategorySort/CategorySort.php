<?php

namespace Paymenter\Extensions\Others\CategorySort;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Category;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\CategorySort\Admin\Resources\CategorySortResource;

#[ExtensionMeta(
    name: 'Category Sort',
    description: 'Drag-and-drop reordering of categories in the admin panel. Controls which categories appear first in the client storefront.',
    version: '1.0.0',
    author: 'Azion Cloud',
    icon: 'ri-drag-move-line'
)]
class CategorySort extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name'  => 'notice',
                'type'  => 'placeholder',
                'label' => new HtmlString(
                    'Drag and drop categories to reorder them via <a class="text-primary-600 font-semibold" href="' .
                    CategorySortResource::getUrl() .
                    '">Category Order</a> in the Administration menu.'
                ),
            ],
        ];
    }

    public function boot()
    {
        // Apply sort order on all Category queries on the public storefront.
        // The sort column is nullable; NULLs fall to the bottom (NULLS LAST behaviour via COALESCE).
        if (!request()->is('admin*')) {
            try {
                if (Schema::hasColumn('categories', 'sort')) {
                    Category::addGlobalScope('sorted', function ($builder) {
                        $builder->orderByRaw('COALESCE(sort, 9999) ASC');
                    });
                }
            } catch (\Exception $e) {
                // Table not ready yet (e.g. during fresh install)
            }
        }

        Event::listen('permissions', function () {
            return [
                'admin.category_sort.view' => 'Manage Category Order',
            ];
        });
    }
}
