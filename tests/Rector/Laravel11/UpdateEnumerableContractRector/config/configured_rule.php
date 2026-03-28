<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateEnumerableContractRector;

return RectorConfig::configure()->withRules([UpdateEnumerableContractRector::class]);