<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateQueueContractMethodsRector;

return RectorConfig::configure()->withRules([UpdateQueueContractMethodsRector::class]);
