<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Support\Compat\CompatFileNotFoundException;

/**
 * Reads manifest files. Decoding is only ever used for *reading* — all
 * writes go through ComposerCli (decision D1).
 */
final class ManifestReader
{
    /**
     * @return array<string, mixed>
     */
    public function readComposerJson(string $workingDirectory): array
    {
        return $this->decode($this->composerJsonPath($workingDirectory));
    }

    public function composerJsonPath(string $workingDirectory): string
    {
        $path = rtrim($workingDirectory, '/').'/composer.json';

        if (! is_file($path)) {
            throw new CompatFileNotFoundException(sprintf(
                'No composer.json found in "%s". Run from the root of a Composer project.',
                $workingDirectory
            ));
        }

        return $path;
    }

    /**
     * Locked/installed packages keyed by name; direct dependencies of both
     * sections. Composer's installed.json is a fallback when a lock snapshot
     * is unavailable, which keeps package-guide analysis useful after install.
     *
     * @return array<string, array<string, mixed>>
     */
    public function readLockedPackages(string $workingDirectory): array
    {
        $path = rtrim($workingDirectory, '/').'/composer.lock';

        $locked = [];

        if (is_file($path)) {
            try {
                $this->appendPackages($locked, $this->decode($path), 'lock');
            } catch (CompatFileNotFoundException) {
                // A malformed or unavailable lock must not make the reader
                // invent a version. The installed metadata is still useful
                // when Composer has written it and remains version-authoritative
                // for packages missing from the lock snapshot.
            }
        }

        $installedPath = rtrim($workingDirectory, '/').'/vendor/composer/installed.json';

        if (is_file($installedPath)) {
            try {
                $this->appendPackages($locked, $this->decode($installedPath), 'installed');
            } catch (CompatFileNotFoundException) {
                // Treat unavailable installed metadata as unknown.
            }
        }

        return $locked;
    }

    /**
     * @param  array<string, array<string, mixed>>  $packages
     * @param  array<string, mixed>  $document
     */
    private function appendPackages(array &$packages, array $document, string $source): void
    {
        /** @var array{packages?: list<array<string, mixed>>, packages-dev?: list<array<string, mixed>>} $lock */
        $lock = $document;

        foreach (['packages', 'packages-dev'] as $key) {
            $entries = $lock[$key] ?? null;

            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $package) {
                if (! is_array($package)) {
                    continue;
                }

                $name = $package['name'] ?? null;

                if (is_string($name)) {
                    /** @var array<string, mixed> $package */
                    if (! isset($packages[$name])) {
                        $package['_source'] = $source;
                        $packages[$name] = $package;
                    }
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $path): array
    {
        if (! is_file($path)) {
            throw new CompatFileNotFoundException(sprintf('File "%s" was not found.', $path));
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new CompatFileNotFoundException(sprintf('Could not read "%s".', $path));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            throw new CompatFileNotFoundException(sprintf(
                '"%s" contains invalid JSON: %s',
                $path,
                $jsonException->getMessage()
            ));
        }

        if (! is_array($decoded)) {
            throw new CompatFileNotFoundException(sprintf('"%s" must contain a JSON object.', $path));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
