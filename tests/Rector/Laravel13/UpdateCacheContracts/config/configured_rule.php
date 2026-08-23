<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateCacheRepositoryContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateCacheStoreContractRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([
    UpdateCacheStoreContractRector::class,
    UpdateCacheRepositoryContractRector::class,
]);
