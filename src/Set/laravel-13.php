<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\RenameJobAttemptedEventPropertyRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\RenameQueueBusyEventPropertyRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\RenameValidateCsrfTokensMethodRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateBusDispatcherContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateCacheConfigurationRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateCacheRepositoryContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateCacheStoreContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateContainerCallNullableDefaultsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateDatabaseQueryBehaviorRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateEloquentBehaviorChangesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateHttpClientThrowSignaturesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateMustVerifyEmailContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateNotificationBehaviorRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdatePaginationViewNamesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdatePasswordResetSubjectRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateQueueContractMethodsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateResponseFactoryContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateRoutingDomainPrecedenceRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateSupportBehaviorChangesRector;
use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withConfiguredRule(RenameClassRector::class, [
        // Laravel 13 renamed the CSRF middleware; both legacy aliases are
        // deprecated subclasses of the new class.
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken' => 'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken' => 'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
    ])
    ->withRules([
    RenameValidateCsrfTokensMethodRector::class,
    UpdateCacheConfigurationRector::class,
    UpdateCacheRepositoryContractRector::class,
    UpdateCacheStoreContractRector::class,
    UpdateBusDispatcherContractRector::class,
    UpdateResponseFactoryContractRector::class,
    UpdateMustVerifyEmailContractRector::class,
    UpdateQueueContractMethodsRector::class,
    UpdateContainerCallNullableDefaultsRector::class,
    UpdateDatabaseQueryBehaviorRector::class,
    UpdateEloquentBehaviorChangesRector::class,
    UpdateNotificationBehaviorRector::class,
    RenameJobAttemptedEventPropertyRector::class,
    RenameQueueBusyEventPropertyRector::class,
    UpdateRoutingDomainPrecedenceRector::class,
    UpdatePaginationViewNamesRector::class,
    UpdatePasswordResetSubjectRector::class,
    UpdateSupportBehaviorChangesRector::class,
    UpdateHttpClientThrowSignaturesRector::class,
]);
