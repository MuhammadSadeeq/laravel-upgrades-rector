<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateDatabaseConnectionInterfaceRector;

return RectorConfig::configure()->withRules([UpdateDatabaseConnectionInterfaceRector::class]);