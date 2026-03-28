<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\RemoveSpatieOnceRector;

return RectorConfig::configure()->withRules([RemoveSpatieOnceRector::class]);