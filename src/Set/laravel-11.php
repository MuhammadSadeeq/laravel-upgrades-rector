<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateComposerDependenciesLaravel11Rector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateFloatingPointTypesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateColumnModificationRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateSanctumConfigRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdatePasswordRehashingRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateRateLimitingRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\RemoveSpatieOnceRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\RemoveDoctrineDBALRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateEloquentCastsMethodRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateSpatialTypesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateEnumerableContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateUserProviderContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateAuthenticatableContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateCashierStripeRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateCashierStripeMigrationRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateCashierStripeTrialRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdatePassportRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateTelescopeRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateDatabaseConnectionInterfaceRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateMailerContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateBatchRepositoryInterfaceRector;

return RectorConfig::configure()->withRules([
    // High Impact Changes
    UpdateComposerDependenciesLaravel11Rector::class,
    UpdateFloatingPointTypesRector::class,
    UpdateColumnModificationRector::class,
    UpdateSanctumConfigRector::class,

    // Medium Impact Changes
    UpdatePasswordRehashingRector::class,
    UpdateRateLimitingRector::class,
    RemoveSpatieOnceRector::class,

    // Low Impact Changes
    RemoveDoctrineDBALRector::class,
    UpdateEloquentCastsMethodRector::class,
    UpdateSpatialTypesRector::class,
    UpdateEnumerableContractRector::class,
    UpdateUserProviderContractRector::class,
    UpdateAuthenticatableContractRector::class,
    UpdateDatabaseConnectionInterfaceRector::class,
    UpdateMailerContractRector::class,
    UpdateBatchRepositoryInterfaceRector::class,

    // Package Updates (if installed)
    UpdateCashierStripeRector::class,
    UpdateCashierStripeMigrationRector::class,
    UpdateCashierStripeTrialRector::class,
    UpdatePassportRector::class,
    UpdateTelescopeRector::class,
]);
