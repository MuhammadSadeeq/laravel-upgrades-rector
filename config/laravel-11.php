<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Set\LaravelUpgradeSetList;

return RectorConfig::configure()
    ->withSets([LaravelUpgradeSetList::LARAVEL_11])
    ->withPaths([
        getcwd() . "/app",
        getcwd() . "/config",
        getcwd() . "/database",
        getcwd() . "/routes",
        getcwd() . "/resources",
    ])
    ->withPhpSets(php82: true);
