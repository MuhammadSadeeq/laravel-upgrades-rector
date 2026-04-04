<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateDatabaseSignatureChangesRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdateDatabaseSignatureChangesRector::class]);
