<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\ReplaceHasVersion7UuidsRector;

return RectorConfig::configure()
    ->withRules([
        ReplaceHasVersion7UuidsRector::class,
    ]);
