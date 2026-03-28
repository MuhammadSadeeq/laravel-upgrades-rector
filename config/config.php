<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

// This file is auto-loaded via composer extra.rector.includes.
// It intentionally registers no rules -- users must opt in by including
// specific sets in their own rector.php:
//
//   use MuhammadSadeeq\LaravelUpgradesRector\Set\LaravelUpgradeSetList;
//
//   return RectorConfig::configure()
//       ->withSets([LaravelUpgradeSetList::LARAVEL_12])
//       ->withPaths([__DIR__ . '/app', __DIR__ . '/config', ...]);

return RectorConfig::configure();
