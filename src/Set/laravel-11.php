<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
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
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateAuthenticationExceptionRedirectToRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateCachePrefixConfigRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateEmailVerificationSetupRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateMariaDbUuidColumnRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateQueueAfterCommitRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateSchemaGetColumnTypeRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdatePublishedServiceProviderRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateSparkStripeRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateSqliteVersionRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\Carbon3MigrationRector;

return RectorConfig::configure()->withRules([
    // High Impact Changes
    UpdateFloatingPointTypesRector::class,
    UpdateColumnModificationRector::class,
    UpdateSanctumConfigRector::class,
    UpdateSqliteVersionRector::class,

    // Medium Impact Changes
    Carbon3MigrationRector::class,
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
    UpdateAuthenticationExceptionRedirectToRector::class,
    UpdateEmailVerificationSetupRector::class,
    UpdateCachePrefixConfigRector::class,
    UpdateSchemaGetColumnTypeRector::class,
    UpdateMariaDbUuidColumnRector::class,
    UpdateQueueAfterCommitRector::class,
    UpdatePublishedServiceProviderRector::class,

    // Package Updates (if installed)
    UpdateCashierStripeRector::class,
    UpdateCashierStripeMigrationRector::class,
    UpdateCashierStripeTrialRector::class,
    UpdatePassportRector::class,
    UpdateSparkStripeRector::class,
    UpdateTelescopeRector::class,
]);
