<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateCashierStripeRector;

return RectorConfig::configure()->withRules([UpdateCashierStripeRector::class]);