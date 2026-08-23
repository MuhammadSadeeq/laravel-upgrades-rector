<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\Carbon3MigrationRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\ReplaceHasVersion4UuidsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\ReplaceHasVersion7UuidsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateBlueprintConstructorRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateConcurrencyResultMappingRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateContainerDependencyResolutionRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateDatabaseSignatureChangesRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateDatabaseTokenRepositoryRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateImageValidationSvgRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateRequestMergingRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateRoutePrecedenceRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateSchemaMethodsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\UpdateStorageConfigRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([
    Carbon3MigrationRector::class,
    ReplaceHasVersion4UuidsRector::class,
    ReplaceHasVersion7UuidsRector::class,
    UpdateBlueprintConstructorRector::class,
    UpdateConcurrencyResultMappingRector::class,
    UpdateContainerDependencyResolutionRector::class,
    UpdateDatabaseSignatureChangesRector::class,
    UpdateDatabaseTokenRepositoryRector::class,
    UpdateImageValidationSvgRector::class,
    UpdateRequestMergingRector::class,
    UpdateRoutePrecedenceRector::class,
    UpdateSchemaMethodsRector::class,
    UpdateStorageConfigRector::class,
]);
