<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateMariaDbUuidColumnRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdateMariaDbUuidColumnRector::class]);
