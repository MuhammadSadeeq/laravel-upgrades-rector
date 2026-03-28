<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateConcurrencyResultMappingRector;

return RectorConfig::configure()->withRules([UpdateConcurrencyResultMappingRector::class]);