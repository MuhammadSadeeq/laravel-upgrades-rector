<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateRoutingDomainPrecedenceRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdateRoutingDomainPrecedenceRector::class]);
