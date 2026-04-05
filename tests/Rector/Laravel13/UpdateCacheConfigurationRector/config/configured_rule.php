<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateCacheConfigurationRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdateCacheConfigurationRector::class]);
