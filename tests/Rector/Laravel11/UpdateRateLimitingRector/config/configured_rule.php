<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateRateLimitingRector;

return RectorConfig::configure()->withRules([UpdateRateLimitingRector::class]);