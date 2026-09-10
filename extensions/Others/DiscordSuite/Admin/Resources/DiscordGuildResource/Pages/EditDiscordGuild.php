<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\DiscordGuildResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Resources\DiscordGuildResource;

class EditDiscordGuild extends EditRecord
{
    protected static string $resource = DiscordGuildResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
