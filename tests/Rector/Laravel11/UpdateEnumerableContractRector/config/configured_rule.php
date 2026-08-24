<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateEnumerableContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Tests\Support\EnvAutoload;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withAutoloadPaths(array_filter([EnvAutoload::vendorDirectory()]))->withRules([UpdateEnumerableContractRector::class]);
