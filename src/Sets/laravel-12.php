<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateComposerDependenciesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\Carbon3MigrationRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateImageValidationSvgRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateConcurrencyResultMappingRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateDatabaseTokenRepositoryRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateSchemaMethodsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateBlueprintConstructorRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateRequestMergingRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateStorageConfigRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\ReplaceHasVersion4UuidsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12\UpdateContainerDependencyResolutionRector;

return RectorConfig::configure()->withRules([
    // Core Laravel 12 upgrade rules
    UpdateComposerDependenciesRector::class,
    UpdateImageValidationSvgRector::class,
    UpdateConcurrencyResultMappingRector::class,

    // Carbon 3 migration rules
    Carbon3MigrationRector::class,

    // Additional breaking changes
    UpdateDatabaseTokenRepositoryRector::class,
    UpdateSchemaMethodsRector::class,
    UpdateBlueprintConstructorRector::class,
    UpdateRequestMergingRector::class,
    UpdateStorageConfigRector::class,
    ReplaceHasVersion4UuidsRector::class,
    UpdateContainerDependencyResolutionRector::class,
]);
