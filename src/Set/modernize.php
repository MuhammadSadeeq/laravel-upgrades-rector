<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

/**
 * Optional code-quality set composing driftingly/rector-laravel's
 * Laravel-specific modernizations. NOT included by the version upgrade
 * presets — this is for users who want opinionated cleanups alongside
 * the upgrade.
 *
 * Usage: add LaravelUpgradeSetList::MODERNIZE to your withSets() call.
 */
return RectorConfig::configure()
    ->withSets([
        \RectorLaravel\Set\LaravelSetList::LARAVEL_CODE_QUALITY,
        \RectorLaravel\Set\LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
    ]);
