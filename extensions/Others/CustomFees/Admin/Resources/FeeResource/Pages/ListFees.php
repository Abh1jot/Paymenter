<?php

namespace Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource;

class ListFees extends ListRecords
{
    protected static string $resource = FeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
