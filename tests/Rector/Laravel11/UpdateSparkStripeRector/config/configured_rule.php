<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateSparkStripeRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdateSparkStripeRector::class]);
