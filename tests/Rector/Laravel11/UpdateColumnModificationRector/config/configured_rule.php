<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateColumnModificationRector;

return RectorConfig::configure()->withRules([UpdateColumnModificationRector::class]);