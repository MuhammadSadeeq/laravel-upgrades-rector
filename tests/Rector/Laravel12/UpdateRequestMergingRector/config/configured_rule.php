<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateRequestMergingRector;

return RectorConfig::configure()->withRules([UpdateRequestMergingRector::class]);