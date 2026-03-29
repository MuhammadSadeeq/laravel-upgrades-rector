<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\RenamePreventRequestForgeryMiddlewareRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([RenamePreventRequestForgeryMiddlewareRector::class]);
