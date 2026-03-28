<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateBatchRepositoryInterfaceRector;

return RectorConfig::configure()->withRules([UpdateBatchRepositoryInterfaceRector::class]);