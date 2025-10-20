<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateComposerDependenciesLaravel11Rector;

return RectorConfig::configure()->withRules([UpdateComposerDependenciesLaravel11Rector::class]);