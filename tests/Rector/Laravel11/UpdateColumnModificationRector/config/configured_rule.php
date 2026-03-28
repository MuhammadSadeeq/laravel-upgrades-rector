<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateColumnModificationRector;

return RectorConfig::configure()->withRules([UpdateColumnModificationRector::class]);