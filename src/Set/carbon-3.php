<?php

declare(strict_types=1);

use Composer\Semver\Semver;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3\CarbonDiffInToSignedFloatRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3\CarbonFormatLocalizedToIsoFormatRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3\CarbonNamedArgumentTzRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3\CarbonRemovedMethodsRector;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3\CarbonTimeZoneConstructorRector;
use Rector\Composer\InstalledPackageResolver;
use Rector\Config\RectorConfig;

/**
 * Carbon 2 → 3 migration rules.
 *
 * Gated on the INSTALLED nesbot/carbon major, never on the Laravel version:
 * Laravel 11 accepts both Carbon 2.72+ and 3.x, so only the installed version
 * is a valid trigger (decision D5). When the installed version cannot be
 * resolved (no vendor/composer/installed.json), no rules register — a run
 * against an unknown vendor state must not guess.
 */

$rules = [
    CarbonDiffInToSignedFloatRector::class,
    CarbonRemovedMethodsRector::class,
    CarbonFormatLocalizedToIsoFormatRector::class,
    CarbonNamedArgumentTzRector::class,
    CarbonTimeZoneConstructorRector::class,
];

$builder = RectorConfig::configure();

try {
    $installedPackageResolver = new InstalledPackageResolver((string) getcwd());
    $carbonVersion = $installedPackageResolver->resolvePackageVersion('nesbot/carbon');
} catch (\Throwable) {
    $carbonVersion = null;
}

if (is_string($carbonVersion) && Semver::satisfies($carbonVersion, '^3.0')) {
    $builder = $builder->withRules($rules);
}

return $builder;
