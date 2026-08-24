<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Set\LaravelUpgradeSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withSets([
    LaravelUpgradeSetList::LARAVEL_11,
    LaravelUpgradeSetList::LARAVEL_12,
]);
