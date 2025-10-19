<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateComposerDependenciesLaravel11Rector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateFloatingPointTypesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateColumnModificationRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateSanctumConfigRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdatePasswordRehashingRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateRateLimitingRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\RemoveSpatieOnceRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\RemoveDoctrineDBALRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateEloquentCastsMethodRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateSpatialTypesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateEnumerableContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateUserProviderContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateAuthenticatableContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateCashierStripeRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateCashierStripeMigrationRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateCashierStripeTrialRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdatePassportRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateTelescopeRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateDatabaseConnectionInterfaceRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateMailerContractRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11\UpdateBatchRepositoryInterfaceRector;

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
