<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\RoleMappingResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\RoleMappingResource;

class EditRoleMapping extends EditRecord
{
    protected static string $resource = RoleMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
