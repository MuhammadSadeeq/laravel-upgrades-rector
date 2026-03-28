<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdatePasswordRehashingRector;

return RectorConfig::configure()->withRules([UpdatePasswordRehashingRector::class]);