<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Clusters;

use Filament\Clusters\Cluster;

class DiscordSuiteCluster extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'ri-discord-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-discord-fill';

    public static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Discord Suite';

    protected static ?string $slug = 'discord-suite';
}
