<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\RemoveCashierIgnoreMigrationsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\RemoveDoctrineDBALRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\RemoveSpatieOnceRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\RemoveTelescopeIgnoreMigrationsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\RenameCashierSubscriptionNameToTypeRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateAuthenticationExceptionRedirectToRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateCashierStripeRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateEnumerableDumpSignatureRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateFloatingPointTypesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdatePasswordRehashingRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateRateLimitingRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateSanctumConfigRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11\UpdateSpatialTypesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Shared\ContractSpecLoader;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Shared\ImplementMissingInterfaceMethodsRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([__DIR__.'/carbon-3.php'])
    ->withConfiguredRule(ImplementMissingInterfaceMethodsRector::class, [
        11,
        ...ContractSpecLoader::forMajor(11),
    ])
    ->withRules([
        // High Impact Changes
        UpdateFloatingPointTypesRector::class,
        UpdateSanctumConfigRector::class,

        // Medium Impact Changes
        UpdatePasswordRehashingRector::class,
        UpdateRateLimitingRector::class,
        RemoveSpatieOnceRector::class,
        RemoveTelescopeIgnoreMigrationsRector::class,
        RemoveCashierIgnoreMigrationsRector::class,

        // Low Impact Changes
        RemoveDoctrineDBALRector::class,
        UpdateSpatialTypesRector::class,
        UpdateEnumerableDumpSignatureRector::class,
        UpdateAuthenticationExceptionRedirectToRector::class,

        // Package Updates (if installed)
        UpdateCashierStripeRector::class,
        RenameCashierSubscriptionNameToTypeRector::class,
    ]);

/*
 * Deliberately NOT registered — dead or harmful advisory rules whose checks
 * move to the PHPStan advisory engine, the preflight/post
 * steps or the config merger:
 *
 * - UpdateSqliteVersionRector          → environment preflight (sqlite >= 3.26)
 * - UpdateCachePrefixConfigRector      → matched a shape that never occurs in real configs
 * - UpdateMariaDbUuidColumnRector      → needs the project's database driver as context
 * - UpdateQueueAfterCommitRector       → matched a shape that never occurs in real configs
 * - UpdateCashierStripeMigrationRector → commented migrations users had already written correctly
 * - UpdatePassportRector               → identical comment on every Passport::* call
 * - UpdateTelescopeRector              → identical comment on every Telescope::* call
 * - UpdateSparkStripeRector            → fired only on ignoreMigrations() calls
 */
