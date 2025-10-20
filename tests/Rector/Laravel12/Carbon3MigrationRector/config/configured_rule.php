<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\Carbon3MigrationRector;

return RectorConfig::configure()
    ->withRules([
        Carbon3MigrationRector::class,
    ]);