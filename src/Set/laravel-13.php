<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\RenameJobAttemptedEventPropertyRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\RenamePreventRequestForgeryMiddlewareRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\RenameQueueBusyEventPropertyRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateBusDispatcherContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateCacheStoreContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateComposerDependenciesLaravel13Rector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateHttpClientThrowSignaturesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateMustVerifyEmailContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdatePaginationViewNamesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateQueueContractMethodsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateResponseFactoryContractRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([
    UpdateComposerDependenciesLaravel13Rector::class,
    RenamePreventRequestForgeryMiddlewareRector::class,
    UpdateCacheStoreContractRector::class,
    UpdateBusDispatcherContractRector::class,
    UpdateResponseFactoryContractRector::class,
    UpdateMustVerifyEmailContractRector::class,
    UpdateQueueContractMethodsRector::class,
    RenameJobAttemptedEventPropertyRector::class,
    RenameQueueBusyEventPropertyRector::class,
    UpdatePaginationViewNamesRector::class,
    UpdateHttpClientThrowSignaturesRector::class,
]);
