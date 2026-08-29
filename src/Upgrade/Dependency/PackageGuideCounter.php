<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use FilesystemIterator;
use SplFileInfo;
use Throwable;

/**
 * A deterministic, read-only count used by guides such as Livewire's
 * component migration. Paths are validated by PackageGuideCatalog before a
 * counter is constructed. Symlinks are deliberately ignored so a guide
 * cannot traverse outside the project or recurse through a cycle. Both the
 * matched-file count and directory-entry inspection are bounded.
 */
final class PackageGuideCounter
{
    public const DEFAULT_MAX_FILES = 10000;

    public const DEFAULT_MAX_DEPTH = 32;

    public const DEFAULT_MAX_ENTRIES = 50000;

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $extensions
     */
    public function __construct(
        public readonly string $label,
        public readonly array $paths,
        public readonly array $extensions,
        public readonly int $maxFiles = self::DEFAULT_MAX_FILES,
        public readonly int $maxDepth = self::DEFAULT_MAX_DEPTH,
        public readonly int $maxEntries = self::DEFAULT_MAX_ENTRIES,
    ) {}

    public function count(string $workingDirectory): int
    {
        if ($this->maxFiles <= 0 || $this->maxDepth < 0 || $this->maxEntries <= 0) {
            return 0;
        }

        try {
            $root = realpath($workingDirectory);
        } catch (Throwable) {
            return 0;
        }

        if ($root === false || ! is_dir($root)) {
            return 0;
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $seen = [];
        $count = 0;
        $entriesVisited = 0;

        foreach ($this->paths as $relativePath) {
            if (! is_string($relativePath)
                || $count >= $this->maxFiles
                || ! $this->isSafeRelativePath($relativePath)) {
                continue;
            }

            $path = $root.'/'.trim($relativePath, '/');

            try {
                if (is_link($path)) {
                    continue;
                }

                $realPath = realpath($path);
            } catch (Throwable) {
                continue;
            }

            if ($realPath === false || ! $this->isWithinRoot($realPath, $root)) {
                continue;
            }

            try {
                if (is_file($realPath)) {
                    if (! $this->visitEntry($entriesVisited)) {
                        break;
                    }

                    $this->countFile($realPath, $seen, $count);
                } elseif (is_dir($realPath)) {
                    $this->scanDirectory($realPath, $root, 0, $seen, $count, $entriesVisited);
                }
            } catch (Throwable) {
                // A guide count is advisory. An unreadable path is skipped
                // deterministically and must not fail dependency planning.
                continue;
            }
        }

        return $count;
    }

    /** @param array<string, true> $seen */
    private function scanDirectory(
        string $directory,
        string $root,
        int $depth,
        array &$seen,
        int &$count,
        int &$entriesVisited,
    ): void {
        if ($count >= $this->maxFiles || $depth > $this->maxDepth || $entriesVisited >= $this->maxEntries) {
            return;
        }

        try {
            $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);
            $entries = [];

            foreach ($iterator as $entry) {
                if (! $this->visitEntry($entriesVisited)) {
                    break;
                }

                if ($entry instanceof SplFileInfo) {
                    $entries[] = $entry->getPathname();
                }
            }

            sort($entries, SORT_STRING);
        } catch (Throwable) {
            return;
        }

        foreach ($entries as $path) {
            if ($count >= $this->maxFiles) {
                return;
            }

            try {
                if (is_link($path)) {
                    continue;
                }

                $realPath = realpath($path);

                if ($realPath === false || ! $this->isWithinRoot($realPath, $root)) {
                    continue;
                }

                if (is_file($realPath)) {
                    $this->countFile($realPath, $seen, $count);
                } elseif (is_dir($realPath)) {
                    $this->scanDirectory($realPath, $root, $depth + 1, $seen, $count, $entriesVisited);
                }
            } catch (Throwable) {
                continue;
            }
        }
    }

    private function visitEntry(int &$entriesVisited): bool
    {
        if ($entriesVisited >= $this->maxEntries) {
            return false;
        }

        $entriesVisited++;

        return true;
    }

    /** @param array<string, true> $seen */
    private function countFile(string $path, array &$seen, int &$count): void
    {
        $realFile = realpath($path);

        if ($realFile === false || isset($seen[$realFile]) || ! $this->hasSupportedExtension($realFile)) {
            return;
        }

        $seen[$realFile] = true;
        $count++;
    }

    private function hasSupportedExtension(string $path): bool
    {
        foreach ($this->extensions as $extension) {
            $extension = ltrim(strtolower(trim($extension)), '.');

            if ($extension === '' || preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/i', $extension) !== 1) {
                continue;
            }

            if (str_ends_with(strtolower($path), '.'.$extension)) {
                return true;
            }
        }

        return false;
    }

    private function isSafeRelativePath(string $path): bool
    {
        $path = trim($path);

        return $path !== '' && $path !== '.' && ! str_starts_with($path, '/')
            && ! str_contains($path, '\\') && preg_match('/^[a-z]:/i', $path) !== 1
            && ! str_contains($path, '..');
    }

    private function isWithinRoot(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, $root.'/');
    }
}
