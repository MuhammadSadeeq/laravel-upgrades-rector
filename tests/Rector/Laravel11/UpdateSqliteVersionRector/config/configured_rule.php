<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateSqliteVersionRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdateSqliteVersionRector::class]);
