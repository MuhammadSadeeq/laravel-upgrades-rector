<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateComposerDependenciesLaravel13Rector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdateComposerDependenciesLaravel13Rector::class]);
