<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use RuntimeException;

/** Resolves vendored skeleton snapshots and their provenance metadata. */
final class SkeletonRepository
{
    private string $root;

    /**
     * @param  string|null  $root  Defaults to the package's resources/skeletons.
     */
    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? dirname(__DIR__, 3).'/resources/skeletons', '/');
    }

    public function path(int $major): string
    {
        return $this->root.'/'.$major;
    }

    public function has(int $major): bool
    {
        return is_dir($this->path($major));
    }

    /**
     * File-level synchronization is safe only for complete snapshots. The
     * repository intentionally ships partial offline fixtures as well, and
     * those must never be interpreted as a list of upstream deletions.
     */
    public function isComplete(int $major): bool
    {
        try {
            return ($this->metadata($major)['complete'] ?? false) === true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        $path = $this->root.'/MANIFEST.json';

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        $manifest = [];

        foreach ($decoded as $key => $value) {
            $manifest[(string) $key] = $value;
        }

        return $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(int $major): array
    {
        $manifest = $this->manifest();
        $entry = $manifest[(string) $major] ?? $manifest[$major] ?? null;

        if (! is_array($entry)) {
            throw new RuntimeException(sprintf('No metadata exists for Laravel %d.', $major));
        }

        // Manifest entries are decoded JSON objects, so their keys are strings.
        /** @var array<string, mixed> $entry */
        return $entry;
    }

    /**
     * @return list<int>
     */
    public function availableMajors(): array
    {
        $majors = [];

        foreach (glob($this->root.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);

            if (ctype_digit($name)) {
                $majors[] = (int) $name;
            }
        }

        sort($majors);

        return $majors;
    }
}
