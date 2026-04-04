<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\Composer;

use Rector\FileSystem\JsonFileSystem;

final class Laravel12ComposerJsonUpdater
{
    /** @var array<string, string> */
    private const DEPENDENCY_UPDATES = [
        'laravel/framework' => '^12.0',
        'phpunit/phpunit' => '^11.0',
        'pestphp/pest' => '^3.0',
        'nunomaduro/collision' => '^8.1',
        'laravel/breeze' => '^2.0',
        'laravel/cashier' => '^15.0',
        'laravel/dusk' => '^8.0',
        'laravel/jetstream' => '^5.0',
        'laravel/passport' => '^12.0',
        'laravel/sanctum' => '^4.0',
        'laravel/scout' => '^10.0',
        'laravel/telescope' => '^5.0',
        'livewire/livewire' => '^3.4',
        'inertiajs/inertia-laravel' => '^2.0',
    ];

    public function update(string $composerJsonPath): bool
    {
        if (! is_file($composerJsonPath)) {
            return false;
        }

        $composerJson = JsonFileSystem::readFilePath($composerJsonPath);
        $changed = false;

        foreach (['require', 'require-dev'] as $section) {
            if (! isset($composerJson[$section]) || ! is_array($composerJson[$section])) {
                continue;
            }

            /** @var array<string, mixed> $sectionDependencies */
            $sectionDependencies = $composerJson[$section];

            foreach (self::DEPENDENCY_UPDATES as $packageName => $targetConstraint) {
                if (! isset($sectionDependencies[$packageName]) || ! is_string($sectionDependencies[$packageName])) {
                    continue;
                }

                $currentConstraint = $sectionDependencies[$packageName];

                if (! $this->shouldUpdateVersion($currentConstraint, $targetConstraint)) {
                    continue;
                }

                $sectionDependencies[$packageName] = $targetConstraint;
                $changed = true;
            }

            $composerJson[$section] = $sectionDependencies;
        }

        if (! $changed) {
            return false;
        }

        JsonFileSystem::writeFile($composerJsonPath, $composerJson);

        return true;
    }

    private function shouldUpdateVersion(string $currentConstraint, string $targetConstraint): bool
    {
        $current = $this->extractHighestVersion($currentConstraint);
        $target = $this->extractHighestVersion($targetConstraint);

        if ($current === null || $target === null) {
            return false;
        }

        return version_compare($current, $target, '<');
    }

    private function extractHighestVersion(string $constraint): ?string
    {
        preg_match_all('/(\d+(?:\.\d+)*)/', $constraint, $matches);

        if ($matches[1] === []) {
            return null;
        }

        /** @var list<non-empty-string> $versions */
        $versions = $matches[1];
        $highestVersion = array_shift($versions);

        if (! is_string($highestVersion)) {
            return null;
        }

        foreach ($versions as $version) {
            if (version_compare($version, $highestVersion, '>')) {
                $highestVersion = $version;
            }
        }

        return $highestVersion;
    }
}
