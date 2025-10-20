<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\ReplaceHasVersion4UuidsRector;

return RectorConfig::configure()
    ->withRules([
        ReplaceHasVersion4UuidsRector::class,
    ]);