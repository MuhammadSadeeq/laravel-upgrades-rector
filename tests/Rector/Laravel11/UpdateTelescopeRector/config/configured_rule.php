<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Tests\Support\EnvAutoload;
use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateTelescopeRector;

return RectorConfig::configure()
    ->withAutoloadPaths(array_filter([EnvAutoload::vendorDirectory()]))->withRules([UpdateTelescopeRector::class]);
