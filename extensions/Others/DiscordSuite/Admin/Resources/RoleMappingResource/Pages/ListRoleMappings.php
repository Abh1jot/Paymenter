<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\RoleMappingResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\RoleMappingResource;

class ListRoleMappings extends ListRecords
{
    protected static string $resource = RoleMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Create Role Rule'),
        ];
    }
}
