<?php

namespace Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource;

class EditFee extends EditRecord
{
    protected static string $resource = FeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
