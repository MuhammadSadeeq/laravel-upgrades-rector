<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateCacheRepositoryContractRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdateCacheRepositoryContractRector::class]);
