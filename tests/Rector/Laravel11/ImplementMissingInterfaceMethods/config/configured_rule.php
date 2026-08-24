<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Shared\ContractSpecLoader;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Shared\ImplementMissingInterfaceMethodsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Tests\Support\EnvAutoload;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withAutoloadPaths(array_filter([EnvAutoload::vendorDirectory()]))
    ->withConfiguredRule(ImplementMissingInterfaceMethodsRector::class, [
        11,
        ...ContractSpecLoader::forMajor(11),
    ]);
