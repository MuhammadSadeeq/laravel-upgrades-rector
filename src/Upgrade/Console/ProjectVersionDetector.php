<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console;

use JsonException;

/**
 * Detects Laravel from the most authoritative local Composer metadata.
 * Installed package metadata wins over the lockfile, which wins over the
 * manifest constraint (the latter is only an estimate before installation).
 */
final class ProjectVersionDetector
{
    public function detect(string $workingDirectory): ProjectVersionDetection
    {
        $directory = rtrim($workingDirectory, '/\\');
        $fallbackWarnings = [];

        foreach ([
            [$directory.'/vendor/composer/installed.json', 'vendor/composer/installed.json'],
            [$directory.'/composer.lock', 'composer.lock'],
            [$directory.'/composer.json', 'composer.json'],
        ] as [$path, $source]) {
            if (! is_file($path)) {
                continue;
            }

            $decoded = $this->decode($path);

            if ($decoded === null) {
                $fallbackWarnings[] = sprintf('%s could not be parsed.', $source);

                continue;
            }

            $version = $source === 'composer.json'
                ? $this->manifestVersion($decoded)
                : $this->packageVersion($decoded);

            if ($version === null) {
                $fallbackWarnings[] = sprintf('%s does not contain laravel/framework.', $source);

                continue;
            }

            $major = $this->major($version);

            if ($major === null) {
                $fallbackWarnings[] = sprintf('%s contains an unrecognised Laravel version.', $source);

                continue;
            }

            $warning = $source === 'vendor/composer/installed.json'
                ? null
                : sprintf(
                    'Laravel %d was inferred from %s; installed package metadata was unavailable (%s).',
                    $major,
                    $source,
                    $fallbackWarnings === [] ? 'no vendor/composer/installed.json was found.' : implode(' ', $fallbackWarnings),
                );

            return new ProjectVersionDetection($major, $source, $warning);
        }

        return new ProjectVersionDetection(
            null,
            'unknown',
            $fallbackWarnings === []
                ? 'Could not detect laravel/framework from local Composer metadata.'
                : implode(' ', $fallbackWarnings),
        );
    }

    /** @return array<int|string, mixed>|null */
    private function decode(string $path): ?array
    {
        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<int|string, mixed> $metadata */
    private function packageVersion(array $metadata): ?string
    {
        $packages = [];

        foreach (['packages', 'packages-dev'] as $key) {
            $entries = $metadata[$key] ?? null;

            if (is_array($entries)) {
                $packages = array_merge($packages, $entries);
            }
        }

        if ($packages === []) {
            $packages = $metadata;
        }

        foreach ($packages as $package) {
            if (! is_array($package) || ($package['name'] ?? null) !== 'laravel/framework') {
                continue;
            }

            foreach (['version', 'pretty_version'] as $key) {
                if (is_string($package[$key] ?? null)) {
                    return $package[$key];
                }
            }
        }

        return null;
    }

    /** @param array<int|string, mixed> $manifest */
    private function manifestVersion(array $manifest): ?string
    {
        $require = $manifest['require'] ?? null;

        if (! is_array($require) || ! is_string($require['laravel/framework'] ?? null)) {
            return null;
        }

        return $require['laravel/framework'];
    }

    private function major(string $versionOrConstraint): ?int
    {
        if (preg_match('/(?:^|[\\s|<>=~^*])v?(\\d+)(?:\\.\\d+)?/', $versionOrConstraint, $matches) !== 1) {
            return null;
        }

        $major = (int) $matches[1];

        return $major > 0 ? $major : null;
    }
}
