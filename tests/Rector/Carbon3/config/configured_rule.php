<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3\CarbonDiffInToSignedFloatRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3\CarbonFormatLocalizedToIsoFormatRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3\CarbonNamedArgumentTzRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3\CarbonRemovedMethodsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3\CarbonTimeZoneConstructorRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([
    CarbonDiffInToSignedFloatRector::class,
    CarbonRemovedMethodsRector::class,
    CarbonFormatLocalizedToIsoFormatRector::class,
    CarbonNamedArgumentTzRector::class,
    CarbonTimeZoneConstructorRector::class,
]);
