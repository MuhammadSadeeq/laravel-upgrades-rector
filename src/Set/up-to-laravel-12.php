<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Set\LaravelUpgradeSetList;

return RectorConfig::configure()->withSets([
    LaravelUpgradeSetList::LARAVEL_11,
    LaravelUpgradeSetList::LARAVEL_12,
]);
