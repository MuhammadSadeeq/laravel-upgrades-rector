<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\LaravelUpgradeSetList;

return RectorConfig::configure()
    ->withSets([
        LaravelUpgradeSetList::LARAVEL_12,
    ])
    ->withPaths([
        __DIR__ . '/../../app',
        __DIR__ . '/../../config',
        __DIR__ . '/../../database',
        __DIR__ . '/../../routes',
        __DIR__ . '/../../resources',
    ])
    ->withPhpSets(php83: true);