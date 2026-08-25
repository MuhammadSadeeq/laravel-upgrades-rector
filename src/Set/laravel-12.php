<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\ReplaceHasVersion7UuidsRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([__DIR__.'/carbon-3.php'])
    ->withRules([
        ReplaceHasVersion7UuidsRector::class,
    ]);

/*
 * Deliberately NOT registered — dead or harmful advisory rules whose checks
 * move to the Phase 3 PHPStan advisory engine or the Phase 5 config merger:
 *
 * - UpdateComposerDependenciesRector          → `laravel-upgrade deps` command (P0-02)
 * - ReplaceHasVersion4UuidsRector             → contradicted the v7 rule on the same import
 * - UpdateBlueprintConstructorRector          → flagged already-correct Laravel 12 code forever
 * - UpdateContainerDependencyResolutionRector → fired on every constructor with a defaulted
 *                                               class-typed parameter and collapsed its formatting
 * - UpdateDatabaseTokenRepositoryRector       → multiplied already-correct second values by 60 (security)
 * - UpdateRoutePrecedenceRector               → stateful, in-file only; route analysis moves to verification
 * - UpdateStorageConfigRector                 → matched a shape that never occurs in real configs
 */
