<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\LaravelUpgradeSetList;

// Default configuration
// Users can override this by creating their own rector.php file

return RectorConfig::configure()
    ->withSets([
        // Uncomment the upgrade path you need:
        // LaravelUpgradeSetList::LARAVEL_11, // For Laravel 10→11
    ])
    ->withPaths([
        __DIR__ . "/../../app",
        __DIR__ . "/../../config",
        __DIR__ . "/../../database",
        __DIR__ . "/../../routes",
        __DIR__ . "/../../resources",
    ]);
