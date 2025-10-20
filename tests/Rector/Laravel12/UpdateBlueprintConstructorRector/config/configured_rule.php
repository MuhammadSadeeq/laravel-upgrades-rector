<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateBlueprintConstructorRector;

return RectorConfig::configure()
    ->withRules([
        UpdateBlueprintConstructorRector::class,
    ]);