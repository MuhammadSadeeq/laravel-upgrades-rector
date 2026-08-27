<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\BinaryResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;

/**
 * Composer operations used by orchestrated steps.
 *
 * Unlike the legacy ComposerCli, this adapter is injectable and returns every
 * process result to its caller. Commands are always passed as argv arrays.
 */
final class ComposerProcessAdapter
{
    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly BinaryResolver $binaryResolver = new BinaryResolver,
    ) {}

    /**
     * @param  array<string, string>  $constraints
     * @return list<ProcessResult>
     */
    public function requirePackages(
        string $workingDirectory,
        array $constraints,
        bool $dev = false,
        ?string $composerBinary = null,
    ): array {
        $results = [];

        foreach ($constraints as $package => $constraint) {
            $arguments = [
                $this->binaryResolver->composerBinary($composerBinary),
                'require',
                sprintf('%s:%s', $package, $constraint),
                '--no-update',
                '--no-interaction',
            ];

            if ($dev) {
                $arguments[] = '--dev';
            }

            $results[] = $this->run($arguments, $workingDirectory);
        }

        return $results;
    }

    /**
     * @param  list<string>  $packages
     * @return list<ProcessResult>
     */
    public function removePackages(
        string $workingDirectory,
        array $packages,
        bool $dev = false,
        ?string $composerBinary = null,
    ): array {
        if ($packages === []) {
            return [];
        }

        $arguments = array_merge(
            [$this->binaryResolver->composerBinary($composerBinary), 'remove'],
            array_values($packages),
            ['--no-update', '--no-interaction'],
        );

        if ($dev) {
            $arguments[] = '--dev';
        }

        return [$this->run($arguments, $workingDirectory)];
    }

    public function validate(string $workingDirectory, ?string $composerBinary = null): ProcessResult
    {
        return $this->run(
            [$this->binaryResolver->composerBinary($composerBinary), 'validate', '--strict', '--no-check-lock'],
            $workingDirectory,
        );
    }

    public function solverDryRun(string $workingDirectory, ?string $composerBinary = null): ProcessResult
    {
        return $this->run(
            [
                $this->binaryResolver->composerBinary($composerBinary),
                'update',
                '--dry-run',
                '--with-all-dependencies',
                '--no-interaction',
            ],
            $workingDirectory,
        );
    }

    /**
     * Preview proposed direct dependency constraints without changing the
     * manifest. Composer's require dry-run accepts all constraints for one
     * dependency section in a single argv request.
     *
     * @param  array<string, string>  $constraints
     */
    public function previewRequirements(
        string $workingDirectory,
        array $constraints,
        bool $dev = false,
        ?string $composerBinary = null,
    ): ProcessResult {
        $arguments = [
            $this->binaryResolver->composerBinary($composerBinary),
            'require',
        ];

        foreach ($constraints as $package => $constraint) {
            $arguments[] = sprintf('%s:%s', $package, $constraint);
        }

        $arguments = array_merge($arguments, [
            '--dry-run',
            '--with-all-dependencies',
            '--no-interaction',
        ]);

        if ($dev) {
            $arguments[] = '--dev';
        }

        return $this->run($arguments, $workingDirectory);
    }

    public function update(string $workingDirectory, ?string $composerBinary = null): ProcessResult
    {
        return $this->run(
            [
                $this->binaryResolver->composerBinary($composerBinary),
                'update',
                '--with-all-dependencies',
                '--no-interaction',
                '--no-progress',
            ],
            $workingDirectory,
        );
    }

    public function dumpAutoload(string $workingDirectory, ?string $composerBinary = null): ProcessResult
    {
        return $this->run(
            [$this->binaryResolver->composerBinary($composerBinary), 'dump-autoload', '--no-interaction'],
            $workingDirectory,
        );
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(array $arguments, string $workingDirectory): ProcessResult
    {
        return $this->processRunner->run(new ProcessRequest($arguments, $workingDirectory));
    }
}
