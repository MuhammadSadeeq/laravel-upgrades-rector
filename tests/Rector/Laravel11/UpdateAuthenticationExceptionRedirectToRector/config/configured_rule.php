<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateAuthenticationExceptionRedirectToRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([UpdateAuthenticationExceptionRedirectToRector::class]);
