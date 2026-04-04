<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateRoutePrecedenceRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdateRoutePrecedenceRector::class]);
