<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdatePublishedServiceProviderRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdatePublishedServiceProviderRector::class]);
