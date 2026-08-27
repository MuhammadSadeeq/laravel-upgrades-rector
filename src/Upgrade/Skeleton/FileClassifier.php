<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Classifies the file-level difference between two skeleton directories.
 *
 * The classifier deliberately uses content hashes for rename detection. A
 * skeleton is small and deterministic, so this is both more useful and less
 * surprising than trying to infer renames from filenames.
 */
final class FileClassifier
{
    /**
     * @return array{added: list<string>, removed: list<string>, modified: list<string>, renamed: array<string, string>}
     */
    public function classify(string $fromDirectory, string $toDirectory): array
    {
        $from = $this->files($fromDirectory);
        $to = $this->files($toDirectory);

        $added = array_values(array_diff(array_keys($to), array_keys($from)));
        $removed = array_values(array_diff(array_keys($from), array_keys($to)));
        $modified = [];

        foreach (array_intersect(array_keys($from), array_keys($to)) as $path) {
            if ($from[$path] !== $to[$path]) {
                $modified[] = $path;
            }
        }

        $renamed = [];
        $addedByHash = [];

        foreach ($added as $path) {
            $addedByHash[$to[$path]][] = $path;
        }

        foreach ($removed as $index => $path) {
            $matches = $addedByHash[$from[$path]] ?? [];

            if ($matches === []) {
                continue;
            }

            $newPath = array_shift($matches);
            $renamed[$path] = $newPath;
            $addedByHash[$from[$path]] = $matches;
            unset($removed[$index]);

            foreach ($added as $addedIndex => $candidate) {
                if ($candidate === $newPath) {
                    unset($added[$addedIndex]);
                    break;
                }
            }
        }

        sort($added);
        sort($removed);
        sort($modified);
        ksort($renamed);

        return [
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
            'renamed' => $renamed,
        ];
    }

    /**
     * Alias used by callers that describe this operation as a diff.
     *
     * @return array{added: list<string>, removed: list<string>, modified: list<string>, renamed: array<string, string>}
     */
    public function diff(string $fromDirectory, string $toDirectory): array
    {
        return $this->classify($fromDirectory, $toDirectory);
    }

    /**
     * @return array<string, string>
     */
    private function files(string $directory): array
    {
        if (! is_dir($directory)) {
            throw new RuntimeException(sprintf('Skeleton directory "%s" does not exist.', $directory));
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if (! $fileInfo->isFile()) {
                continue;
            }

            $absolute = $fileInfo->getPathname();
            $relative = str_replace('\\', '/', substr($absolute, strlen(rtrim($directory, '/')) + 1));
            $hash = md5_file($absolute);

            if ($hash === false) {
                throw new RuntimeException(sprintf('Could not hash skeleton file "%s".', $absolute));
            }

            $files[$relative] = $hash;
        }

        ksort($files);

        return $files;
    }
}
