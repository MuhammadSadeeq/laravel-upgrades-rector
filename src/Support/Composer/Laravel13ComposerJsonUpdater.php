<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\Composer;

use Rector\FileSystem\JsonFileSystem;

final class Laravel13ComposerJsonUpdater
{
    /** @var array<string, string> */
    private const DEPENDENCY_UPDATES = [
        'laravel/framework' => '^13.0',
        'laravel/boost' => '^2.0',
        'laravel/tinker' => '^3.0',
        'phpunit/phpunit' => '^12.0',
        'pestphp/pest' => '^4.0',
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
