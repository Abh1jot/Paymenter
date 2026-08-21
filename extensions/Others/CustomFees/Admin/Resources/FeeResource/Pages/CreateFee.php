<?php

namespace Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Paymenter\Extensions\Others\CustomFees\Admin\Resources\FeeResource;

class CreateFee extends CreateRecord
{
    protected static string $resource = FeeResource::class;
}
