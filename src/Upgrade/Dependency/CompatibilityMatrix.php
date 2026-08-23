<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Support\Compat\CompatFileLoader;

/**
 * Reads resources/compat/packages.json and answers "what is the minimum
 * version of package X that supports Laravel major N?".
 */
final class CompatibilityMatrix
{
    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $packages = null;

    public function __construct(
        private readonly string $packagesJsonPath,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function packages(): array
    {
        if ($this->packages === null) {
            /** @var array<string, array<string, mixed>> $packages */
            $packages = CompatFileLoader::load($this->packagesJsonPath, 'packages');
            $this->packages = $packages;
        }

        return $this->packages;
    }

    /**
     * Lowest version of the package known to support the target major,
     * or null when the matrix has no entry for this combination.
     */
    public function minimumVersionFor(string $package, int $targetMajor): ?string
    {
        $packages = $this->packages();

        if (! array_key_exists($package, $packages)) {
            return null;
        }

        $entry = $packages[$package];
        $key = (string) $targetMajor;

        if (! array_key_exists($key, $entry)) {
            return null;
        }

        $version = $entry[$key];

        return is_string($version) && $version !== '' ? $version : null;
    }

    public function sectionFor(string $package): string
    {
        $entry = $this->packages()[$package] ?? null;

        if (is_array($entry) && ($entry['section'] ?? null) === 'require-dev') {
            return 'require-dev';
        }

        return 'require';
    }

    public function hasOwnGuide(string $package): bool
    {
        $entry = $this->packages()[$package] ?? null;

        return is_array($entry) && (bool) ($entry['hasOwnGuide'] ?? false);
    }

    public function followsMajorOf(string $package): ?string
    {
        $entry = $this->packages()[$package] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        $follows = $entry['followsMajorOf'] ?? null;

        return is_string($follows) && $follows !== '' ? $follows : null;
    }
}
