<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateContainerDependencyResolutionRector;

return RectorConfig::configure()->withRules([UpdateContainerDependencyResolutionRector::class]);
