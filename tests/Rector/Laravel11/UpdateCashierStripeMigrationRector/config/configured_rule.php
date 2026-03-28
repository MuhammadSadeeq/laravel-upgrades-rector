<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateCashierStripeMigrationRector;

return RectorConfig::configure()->withRules([UpdateCashierStripeMigrationRector::class]);