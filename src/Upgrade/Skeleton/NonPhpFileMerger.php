<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;

/**
 * Handles skeleton files which are not PHP configuration arrays.
 *
 * package.json is intentionally advisory-only. JavaScript dependency updates
 * need the project's package manager and are outside Composer's transaction.
 */
final class NonPhpFileMerger
{
    private const THREE_WAY_FILES = [
        'phpunit.xml',
        'vite.config.js',
        '.gitignore',
        '.editorconfig',
    ];

    /**
     * @return array{changed: list<string>, conflicts: list<string>, advisories: list<string>}
     */
    public function sync(
        string $projectDirectory,
        string $fromSkeletonDirectory,
        string $toSkeletonDirectory,
        ?FindingCollector $collector = null,
        bool $dryRun = false
    ): array {
        $result = ['changed' => [], 'conflicts' => [], 'advisories' => []];
        $merger = new ThreeWayMerger;

        foreach (self::THREE_WAY_FILES as $relative) {
            $oursPath = $projectDirectory.'/'.$relative;
            $basePath = $fromSkeletonDirectory.'/'.$relative;
            $theirsPath = $toSkeletonDirectory.'/'.$relative;

            if (! is_file($theirsPath) || ! is_file($oursPath)) {
                continue;
            }

            $ours = file_get_contents($oursPath);
            $base = is_file($basePath) ? file_get_contents($basePath) : '';
            $theirs = file_get_contents($theirsPath);

            if ($ours === false || $theirs === false || $base === false) {
                continue;
            }

            $status = $merger->mergeWithStatus($ours, $base, $theirs);

            if ($status['content'] === $ours) {
                continue;
            }

            $result['changed'][] = $relative;

            if ($status['conflicted']) {
                $result['conflicts'][] = $relative;
                $result['advisories'][] = $relative;

                if ($collector !== null) {
                    $collector->add(
                        'laravelUpgrade.skeletonMergeConflict',
                        Finding::SEVERITY_HIGH,
                        $this->targetMajor($toSkeletonDirectory),
                        $relative,
                        0,
                        sprintf('The customized skeleton file "%s" could not be merged automatically.', $relative),
                        'Resolve the conflict in the generated .laravel-upgrade/merge file and rerun continue.'
                    );
                }

                if (! $dryRun) {
                    $conflictPath = $projectDirectory.'/.laravel-upgrade/merge/'.$relative.'.merged';
                    $this->write($conflictPath, $status['content']);
                }

                continue;
            }

            if (! $dryRun) {
                $this->write($oursPath, $status['content']);
            }
        }

        $packagePath = $projectDirectory.'/package.json';
        $targetPackagePath = $toSkeletonDirectory.'/package.json';

        if (is_file($packagePath) && is_file($targetPackagePath)) {
            $ours = file_get_contents($packagePath);
            $theirs = file_get_contents($targetPackagePath);

            if ($ours !== false && $theirs !== false && trim($ours) !== trim($theirs)) {
                $result['advisories'][] = 'package.json';

                if ($collector !== null) {
                    $collector->add(
                        'laravelUpgrade.packageJsonDependencies',
                        Finding::SEVERITY_INFO,
                        $this->targetMajor($toSkeletonDirectory),
                        'package.json',
                        0,
                        'The target Laravel skeleton changes JavaScript dependencies in package.json.',
                        'Review and apply the Vite, Tailwind, Axios, and related changes with your package manager.'
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Updates the PHPUnit schema while retaining all other project XML.
     */
    public function updatePhpunitSchema(string $path, int $phpunitMajor, bool $dryRun = false): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        $updated = preg_replace(
            '/https:\/\/schema\.phpunit\.de\/\d+(?:\.\d+)*\/phpunit\.xsd/',
            'https://schema.phpunit.de/'.$phpunitMajor.'.0/phpunit.xsd',
            $contents
        );

        if (! is_string($updated) || $updated === $contents) {
            return false;
        }

        if (! $dryRun) {
            $this->write($path, $updated);
        }

        return true;
    }

    private function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $contents);
    }

    private function targetMajor(string $skeletonDirectory): int
    {
        $name = basename(rtrim($skeletonDirectory, '/'));

        return ctype_digit($name) ? (int) $name : 0;
    }
}
