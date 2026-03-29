<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateResponseFactoryContractRector;

return RectorConfig::configure()->withRules([UpdateResponseFactoryContractRector::class]);
