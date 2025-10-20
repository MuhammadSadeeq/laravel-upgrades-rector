<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateComposerDependenciesRector;

return RectorConfig::configure()->withRules([UpdateComposerDependenciesRector::class]);