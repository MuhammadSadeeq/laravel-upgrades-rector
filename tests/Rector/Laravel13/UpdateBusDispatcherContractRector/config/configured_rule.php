<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Tests\Support\EnvAutoload;
use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateBusDispatcherContractRector;

return RectorConfig::configure()
    ->withAutoloadPaths(array_filter([EnvAutoload::vendorDirectory()]))->withRules([UpdateBusDispatcherContractRector::class]);
