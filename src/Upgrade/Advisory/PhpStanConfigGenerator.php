<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory;

use RuntimeException;

/**
 * Generates the per-run PHPStan configuration used by AdvisoryStep.
 *
 * The generated configuration is deliberately separate from the packaged
 * rule neon: the orchestrator can add project facts without modifying either
 * the package or the application's configuration. The application's general
 * PHPStan configuration is intentionally not included: its unrelated rules,
 * paths, and level must not leak into upgrade advisories.
 */
final class PhpStanConfigGenerator
{
    public function __construct(private readonly string $packageRoot = __DIR__.'/../../..') {}

    /**
     * @param  list<string>  $databaseDrivers
     */
    public function generate(
        string $workingDirectory,
        int $targetMajor,
        string $outputDirectory,
        array $databaseDrivers = [],
        ?string $queueDefault = null,
        ?string $sessionSerialization = null,
    ): string {
        if (! in_array($targetMajor, [11, 12, 13], true)) {
            throw new RuntimeException(sprintf('Unsupported Laravel advisory target %d.', $targetMajor));
        }

        $packageNeon = realpath($this->packageRoot.'/resources/phpstan/upgrade-'.$targetMajor.'.neon');

        if ($packageNeon === false || ! is_file($packageNeon)) {
            throw new RuntimeException(sprintf('Packaged PHPStan config for Laravel %d was not found.', $targetMajor));
        }

        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0777, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException(sprintf('Could not create PHPStan config directory "%s".', $outputDirectory));
        }

        $includes = [$packageNeon];

        $larastan = realpath($workingDirectory.'/vendor/larastan/larastan/extension.neon');

        if ($larastan !== false && is_file($larastan)) {
            $includes[] = $larastan;
        }

        $includeLines = implode("\n", array_map(
            fn (string $path): string => '    - '.$this->neonString($path),
            $includes,
        ));
        $drivers = $databaseDrivers === []
            ? '[]'
            : "\n".implode("\n", array_map(
                fn (string $driver): string => '            - '.$this->neonString($driver),
                $databaseDrivers,
            ));
        $queue = $queueDefault === null ? 'null' : $this->neonString($queueDefault);
        $serialization = $sessionSerialization === null ? 'null' : $this->neonString($sessionSerialization);
        $contents = <<<NEON
includes:
{$includeLines}

parametersSchema:
    laravelUpgrade: structure({
        databaseDrivers: listOf(string()),
        queueDefault: schema(string(), nullable()),
        sessionSerialization: schema(string(), nullable())
    })

parameters:
    laravelUpgrade:
        databaseDrivers: {$drivers}
        queueDefault: {$queue}
        sessionSerialization: {$serialization}

NEON;

        $path = rtrim($outputDirectory, '/\\').'/phpstan-'.$targetMajor.'.neon';

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Could not write PHPStan config "%s".', $path));
        }

        return $path;
    }

    private function neonString(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
}
