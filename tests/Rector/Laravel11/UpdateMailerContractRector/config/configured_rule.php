<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateMailerContractRector;

return RectorConfig::configure()->withRules([UpdateMailerContractRector::class]);