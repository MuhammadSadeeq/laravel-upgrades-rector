<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateAuthenticatableContractRector;

return RectorConfig::configure()->withRules([UpdateAuthenticatableContractRector::class]);