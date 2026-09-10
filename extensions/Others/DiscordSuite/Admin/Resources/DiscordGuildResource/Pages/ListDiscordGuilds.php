<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\DiscordGuildResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\DiscordGuildResource;

class ListDiscordGuilds extends ListRecords
{
    protected static string $resource = DiscordGuildResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Discord Server'),
        ];
    }
}
