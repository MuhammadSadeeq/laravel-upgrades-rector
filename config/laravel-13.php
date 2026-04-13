<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Set\LaravelUpgradeSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([
        LaravelUpgradeSetList::LARAVEL_13,
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
