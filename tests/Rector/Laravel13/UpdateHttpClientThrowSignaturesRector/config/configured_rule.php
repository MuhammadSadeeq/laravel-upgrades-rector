<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateHttpClientThrowSignaturesRector;

return RectorConfig::configure()->withRules([UpdateHttpClientThrowSignaturesRector::class]);
