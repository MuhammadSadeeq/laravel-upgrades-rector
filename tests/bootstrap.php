<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for the per-environment rule suites.
 *
 * When LARAVEL_ENV is set (11|12|13), the matching real framework vendor tree
 * under tests/env/ is loaded BEFORE this package's autoloader, so every
 * Illuminate/Carbon/Cashier symbol resolves to the genuine framework code —
 * the type stubs are gone on purpose (plan P1-01). Rule suites that do not
 * match the active environment skip themselves via AbstractUpgradeRectorTestCase.
 */

$laravelEnv = getenv('LARAVEL_ENV');

if (is_string($laravelEnv) && in_array($laravelEnv, ['11', '12', '13'], true)) {
    $envAutoloader = __DIR__ . '/env/laravel-' . $laravelEnv . '/vendor/autoload.php';

    if (! is_file($envAutoloader)) {
        fwrite(STDERR, sprintf(
            "LARAVEL_ENV=%s is set but %s is missing.\n"
            . "Run `composer install` inside tests/env/laravel-%s first "
            . "(or drop LARAVEL_ENV to run only environment-independent suites).\n",
            $laravelEnv,
            $envAutoloader,
            $laravelEnv
        ));

        exit(1);
    }

    require $envAutoloader;
}

require __DIR__ . '/../vendor/autoload.php';
