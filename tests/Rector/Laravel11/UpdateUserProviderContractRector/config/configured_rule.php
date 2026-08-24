<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateUserProviderContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Tests\Support\EnvAutoload;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withAutoloadPaths(array_filter([EnvAutoload::vendorDirectory()]))->withRules([UpdateUserProviderContractRector::class]);
