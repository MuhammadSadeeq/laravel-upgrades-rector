<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use MuhammadSadeeq\LaravelUpgradesRector\Set\LaravelUpgradeSetList;

/**
 * Direct-Rector preset for upgrading a Laravel 10/11 application to Laravel 11.
 *
 * Paths are resolved from the invocation directory and filtered with is_dir()
 * so the preset works from any cwd (including subdirectories) instead of
 * aborting. Blade templates are never parsed; tests are included.
 */
$larastanExtension = getcwd() . '/vendor/larastan/larastan/extension.neon';

$projectPaths = array_values(array_filter([
    is_dir(getcwd() . '/app') ? getcwd() . '/app' : null,
    is_dir(getcwd() . '/bootstrap') ? getcwd() . '/bootstrap' : null,
    is_dir(getcwd() . '/config') ? getcwd() . '/config' : null,
    is_dir(getcwd() . '/database') ? getcwd() . '/database' : null,
    is_dir(getcwd() . '/routes') ? getcwd() . '/routes' : null,
    is_dir(getcwd() . '/tests') ? getcwd() . '/tests' : null,
]));

// Running from a subdirectory (or a non-Laravel cwd): process nothing but
// exit cleanly instead of erroring on an empty path list.
if ($projectPaths === []) {
    $paths = [getcwd()];
    $skips = ['*'];
} else {
    $paths = $projectPaths;
    $skips = [
        getcwd() . '/bootstrap/cache',
        getcwd() . '/storage',
        getcwd() . '/vendor',
        getcwd() . '/node_modules',
        getcwd() . '/public',
        '*.blade.php',
    ];
}

$builder = RectorConfig::configure()
    ->withSets([
        LaravelUpgradeSetList::LARAVEL_11,
        __DIR__ . '/../src/Set/carbon-3.php',
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withPaths($paths)
    ->withSkip($skips);

if (is_file($larastanExtension)) {
    $builder->withPHPStanConfigs([$larastanExtension]);
}

return $builder;
