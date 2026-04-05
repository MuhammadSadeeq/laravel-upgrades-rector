<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateNotificationBehaviorRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdateNotificationBehaviorRector::class]);
