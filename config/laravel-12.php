<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Set\LaravelUpgradeSetList;

return RectorConfig::configure()
    ->withSets([
        LaravelUpgradeSetList::LARAVEL_12,
    ])
    ->withPaths([
        getcwd() . '/app',
        getcwd() . '/bootstrap',
        getcwd() . '/config',
        getcwd() . '/database',
        getcwd() . '/routes',
        getcwd() . '/resources',
    ])
    ->withSkip([
        getcwd() . '/bootstrap/cache',
    ]);
