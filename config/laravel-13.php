<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use MuhammadSadeeq\LaravelUpgradesRector\Set\LaravelUpgradeSetList;

/**
 * Direct-Rector preset for upgrading a Laravel 12 application to Laravel 13.
 * See config/laravel-11.php for the path/skip policy notes. Laravel 13
 * requires PHP >= 8.3.
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
        LaravelUpgradeSetList::LARAVEL_13,
        __DIR__ . '/../src/Set/carbon-3.php',
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withPaths($paths)
    ->withSkip($skips);

if (is_file($larastanExtension)) {
    $builder->withPHPStanConfigs([$larastanExtension]);
}

return $builder;
