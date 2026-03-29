<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateMustVerifyEmailContractRector;

return RectorConfig::configure()->withRules([UpdateMustVerifyEmailContractRector::class]);
