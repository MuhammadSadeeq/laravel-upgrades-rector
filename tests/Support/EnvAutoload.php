<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Support;

/**
 * Locates the active real-framework vendor tree for the rule test suites.
 * configured_rule.php files pass this path to RectorConfig::withAutoloadPaths()
 * so the test kernel's PHPStan reflection sees the genuine framework classes.
 */
final class EnvAutoload
{
    /**
     * The vendor directory of the active LARAVEL_ENV, or null when running
     * environment-independent suites.
     */
    public static function vendorDirectory(): ?string
    {
        $laravelEnv = getenv('LARAVEL_ENV');

        if (! is_string($laravelEnv) || ! in_array($laravelEnv, ['11', '12', '13'], true)) {
            return null;
        }

        $directory = dirname(__DIR__) . '/env/laravel-' . $laravelEnv . '/vendor';

        return is_dir($directory) ? $directory : null;
    }
}
