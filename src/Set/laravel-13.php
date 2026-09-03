<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\RenameJobAttemptedEventPropertyRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\RenameQueueBusyEventPropertyRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\RenameValidateCsrfTokensMethodRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdateHttpClientThrowSignaturesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\UpdatePaginationViewNamesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Shared\ContractSpecLoader;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Shared\ImplementMissingInterfaceMethodsRector;
use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withConfiguredRule(ImplementMissingInterfaceMethodsRector::class, [
        13,
        ...ContractSpecLoader::forMajor(13),
    ])
    ->withConfiguredRule(RenameClassRector::class, [
        // Laravel 13 renamed the CSRF middleware; both legacy aliases are
        // deprecated subclasses of the new class.
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken' => 'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken' => 'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
    ])
    ->withRules([
        RenameValidateCsrfTokensMethodRector::class,
        RenameJobAttemptedEventPropertyRector::class,
        RenameQueueBusyEventPropertyRector::class,
        UpdatePaginationViewNamesRector::class,
        UpdateHttpClientThrowSignaturesRector::class,
    ]);

/*
 * Deliberately NOT registered — dead or harmful advisory rules whose checks
 * move to the PHPStan advisory engine, the config merger or
 * the verification step:
 *
 * - UpdateCacheConfigurationRector       → wrong session-cookie guidance; all fixtures were no-ops
 * - UpdateNotificationBehaviorRector     → targeted the cohort that is already safe
 * - UpdateRoutingDomainPrecedenceRector  → stateful, same-file only; route analysis moves to verification
 * - UpdateSupportBehaviorChangesRector   → withSchedule branch unreachable, Str branch dead, misses facade ::extend
 */
