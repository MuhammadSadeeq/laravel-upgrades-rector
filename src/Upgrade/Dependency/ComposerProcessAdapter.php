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

    /**
     * Preview all proposed direct dependency changes in one isolated Composer
     * project. Composer's --dev flag applies to an entire require invocation,
     * so separate production/dev previews can report a false solver failure
     * when the two sections need coordinated upgrades.
     *
     * The source manifest and lockfile are copied into a temporary directory,
     * edited there, and solved with one update --dry-run. The caller's project
     * is never modified, including when Composer or temporary setup fails.
     *
     * @param  array<string, array<string, string>>  $constraintsBySection
     * @param  array<string, list<string>>  $removalsBySection
     */
    public function previewRequirementsTogether(
        string $workingDirectory,
        array $constraintsBySection,
        array $removalsBySection = [],
        ?string $composerBinary = null,
    ): ProcessResult {
        $temporaryDirectory = $this->createPreviewDirectory();

        try {
            $this->preparePreviewProject($workingDirectory, $temporaryDirectory, $constraintsBySection, $removalsBySection);

            return $this->run(
                [
                    $this->previewComposerBinary($workingDirectory, $composerBinary),
                    'update',
                    '--dry-run',
                    '--with-all-dependencies',
                    '--no-interaction',
                ],
                $temporaryDirectory,
                $this->previewEnvironment($temporaryDirectory),
            );
        } finally {
            $this->removePreviewDirectory($temporaryDirectory);
        }
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
     * @param  array<string, string>|null  $environment
     */
    private function run(array $arguments, string $workingDirectory, ?array $environment = null): ProcessResult
    {
        return $this->processRunner->run(new ProcessRequest($arguments, $workingDirectory, environment: $environment));
    }

    /** @return non-empty-string */
    private function createPreviewDirectory(): string
    {
        $directory = rtrim(sys_get_temp_dir(), '/\\').'/laravel-upgrade-composer-preview-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Could not create Composer preview workspace.');
        }

        return $directory;
    }

    /**
     * @param  array<string, array<string, string>>  $constraintsBySection
     * @param  array<string, list<string>>  $removalsBySection
     */
    private function preparePreviewProject(
        string $workingDirectory,
        string $temporaryDirectory,
        array $constraintsBySection,
        array $removalsBySection,
    ): void {
        $sourceManifest = rtrim($workingDirectory, '/\\').'/composer.json';
        $contents = file_get_contents($sourceManifest);

        if ($contents === false) {
            throw new \RuntimeException('Could not read composer.json for the Composer preview.');
        }

        try {
            $manifest = json_decode($contents, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Could not decode composer.json for the Composer preview.', 0, $exception);
        }

        if (! $manifest instanceof \stdClass) {
            throw new \RuntimeException('composer.json must contain an object for the Composer preview.');
        }

        $this->canonicalizePathRepositories($manifest, $workingDirectory);

        foreach (['require', 'require-dev'] as $section) {
            $sectionValues = property_exists($manifest, $section) ? $manifest->{$section} : new \stdClass;

            if (! $sectionValues instanceof \stdClass) {
                throw new \RuntimeException(sprintf('composer.json %s must be an object for the Composer preview.', $section));
            }

            foreach ($removalsBySection[$section] ?? [] as $package) {
                unset($sectionValues->{$package});
            }

            foreach ($constraintsBySection[$section] ?? [] as $package => $constraint) {
                $sectionValues->{$package} = $constraint;
            }

            if (get_object_vars($sectionValues) !== [] || property_exists($manifest, $section)) {
                $manifest->{$section} = $sectionValues;
            }
        }

        try {
            $previewManifest = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Could not encode composer.json for the Composer preview.', 0, $exception);
        }

        $manifestPath = $temporaryDirectory.'/composer.json';

        if (file_put_contents($manifestPath, $previewManifest, LOCK_EX) !== strlen($previewManifest)) {
            throw new \RuntimeException('Could not write the Composer preview manifest.');
        }

        $sourceLock = rtrim($workingDirectory, '/\\').'/composer.lock';

        if (is_file($sourceLock) && ! copy($sourceLock, $temporaryDirectory.'/composer.lock')) {
            throw new \RuntimeException('Could not copy composer.lock for the Composer preview.');
        }

        $sourceAuth = rtrim($workingDirectory, '/\\').'/auth.json';

        if (is_file($sourceAuth)) {
            $temporaryAuth = $temporaryDirectory.'/auth.json';

            if (! copy($sourceAuth, $temporaryAuth) || ! chmod($temporaryAuth, 0600)) {
                throw new \RuntimeException('Could not securely copy auth.json for the Composer preview.');
            }
        }
    }

    /** @return array<string, string> */
    private function previewEnvironment(string $temporaryDirectory): array
    {
        foreach ([$temporaryDirectory.'/cache', $temporaryDirectory.'/vendor/bin'] as $directory) {
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new \RuntimeException(sprintf('Could not create Composer preview environment directory %s.', $directory));
            }
        }

        return [
            'COMPOSER' => $temporaryDirectory.'/composer.json',
            'COMPOSER_CACHE_DIR' => $temporaryDirectory.'/cache',
            'COMPOSER_VENDOR_DIR' => $temporaryDirectory.'/vendor',
            'COMPOSER_BIN_DIR' => $temporaryDirectory.'/vendor/bin',
        ];
    }

    private function previewComposerBinary(string $workingDirectory, ?string $composerBinary): string
    {
        $selected = $composerBinary;

        if ($selected === null || $selected === '') {
            $environmentBinary = getenv('COMPOSER_BINARY');
            $selected = is_string($environmentBinary) && $environmentBinary !== '' ? $environmentBinary : null;
        }

        $binary = $this->binaryResolver->composerBinary($selected);

        $isPathLike = $selected !== null && (str_contains($selected, '/') || str_contains($selected, '\\'));
        $isProjectBinary = $selected !== null && is_file(rtrim($workingDirectory, '/\\').'/'.$selected);

        if ($selected !== null && ! $this->isAbsolutePath($selected) && ($isPathLike || $isProjectBinary)) {
            $projectBinary = rtrim($workingDirectory, '/\\').'/'.$selected;
            $resolvedBinary = realpath($projectBinary);

            return $resolvedBinary !== false ? $resolvedBinary : $projectBinary;
        }

        return $binary;
    }

    private function canonicalizePathRepositories(\stdClass $manifest, string $workingDirectory): void
    {
        if (! property_exists($manifest, 'repositories')) {
            return;
        }

        $repositories = $manifest->repositories;

        if (! is_array($repositories) && ! $repositories instanceof \stdClass) {
            return;
        }

        $repositoryEntries = is_array($repositories) ? $repositories : get_object_vars($repositories);

        foreach ($repositoryEntries as $repository) {
            if (! $repository instanceof \stdClass || ($repository->type ?? null) !== 'path'
                || ! is_string($repository->url ?? null) || $repository->url === ''
                || $this->isAbsolutePath($repository->url)) {
                continue;
            }

            $repository->url = $this->absolutePath($workingDirectory, $repository->url);
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function absolutePath(string $root, string $path): string
    {
        $candidate = rtrim($root, '/\\').'/'.$path;
        $resolved = realpath($candidate);

        return $resolved !== false ? $resolved : $candidate;
    }

    private function removePreviewDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            throw new \RuntimeException('Could not inspect Composer preview workspace during cleanup.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            if (is_dir($path) && ! is_link($path)) {
                $this->removePreviewDirectory($path);
            } elseif (! unlink($path)) {
                throw new \RuntimeException(sprintf('Could not remove Composer preview artifact %s.', $path));
            }
        }

        if (! rmdir($directory)) {
            throw new \RuntimeException(sprintf('Could not remove Composer preview workspace %s.', $directory));
        }
    }
}
