<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use Closure;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use RuntimeException;

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

    /** @var Closure(string): ?int */
    private readonly Closure $phpunitMajorResolver;

    public function __construct(?callable $phpunitMajorResolver = null)
    {
        if ($phpunitMajorResolver === null) {
            $this->phpunitMajorResolver = fn (string $projectDirectory): ?int => $this->detectPhpunitMajor($projectDirectory);

            return;
        }

        $resolver = Closure::fromCallable($phpunitMajorResolver);
        $this->phpunitMajorResolver = static function (string $projectDirectory) use ($resolver): ?int {
            $major = $resolver($projectDirectory);

            return is_int($major) ? $major : null;
        };
    }

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
        $changed = $this->pathList();
        $conflicts = $this->pathList();
        $advisories = $this->pathList();
        $merger = new ThreeWayMerger;
        $phpunitConflict = false;
        $phpunitMajor = ($this->phpunitMajorResolver)($projectDirectory);

        foreach (self::THREE_WAY_FILES as $relative) {
            $oursPath = $projectDirectory.'/'.$relative;
            $basePath = $fromSkeletonDirectory.'/'.$relative;
            $theirsPath = $toSkeletonDirectory.'/'.$relative;

            if (! is_file($theirsPath)) {
                continue;
            }

            // A file absent from the project is an upstream addition only
            // when it was also absent from the base snapshot. If it existed
            // in the base, preserve the project's intentional removal.
            if (! is_file($oursPath)) {
                if (is_file($basePath)) {
                    continue;
                }

                $contents = file_get_contents($theirsPath);

                if ($contents === false) {
                    throw new RuntimeException(sprintf('Could not read target non-PHP file "%s".', $theirsPath));
                }

                $changed[] = $relative;

                if (! $dryRun) {
                    $this->write($oursPath, $contents, $this->fileMode($theirsPath));
                }

                continue;
            }

            $ours = file_get_contents($oursPath);
            $base = is_file($basePath) ? file_get_contents($basePath) : '';
            $theirs = file_get_contents($theirsPath);

            if ($ours === false || $theirs === false || $base === false) {
                continue;
            }

            // Do not infer an installed PHPUnit version from the target
            // skeleton. Without lock/vendor provenance, retain the project's
            // schema URL while still merging the rest of the XML.
            if ($relative === 'phpunit.xml' && $phpunitMajor === null) {
                $theirs = $this->preservePhpunitSchema($ours, $theirs);
            }

            $status = $merger->mergeWithStatus($ours, $base, $theirs);

            if ($status['content'] === $ours) {
                continue;
            }

            $changed[] = $relative;

            if ($status['conflicted']) {
                if ($relative === 'phpunit.xml') {
                    $phpunitConflict = true;
                }

                $conflicts[] = $relative;
                $advisories[] = $relative;

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

        if (! $phpunitConflict && $phpunitMajor !== null) {
            if ($this->updatePhpunitSchema(
                $projectDirectory.'/phpunit.xml',
                $phpunitMajor,
                $dryRun,
            ) && ! in_array('phpunit.xml', $changed, true)) {
                $changed[] = 'phpunit.xml';
            }
        }

        $packagePath = $projectDirectory.'/package.json';
        $targetPackagePath = $toSkeletonDirectory.'/package.json';

        if (is_file($targetPackagePath) && ! is_file($packagePath)) {
            $advisories[] = 'package.json';

            if ($collector !== null) {
                $collector->add(
                    'laravelUpgrade.packageJsonDependencies',
                    Finding::SEVERITY_INFO,
                    $this->targetMajor($toSkeletonDirectory),
                    'package.json',
                    0,
                    'The target Laravel skeleton adds package.json, but the project has no JavaScript manifest.',
                    'Review the target JavaScript dependencies and create or update package.json with your package manager.',
                );
            }
        } elseif (is_file($packagePath) && is_file($targetPackagePath)) {
            $ours = file_get_contents($packagePath);
            $theirs = file_get_contents($targetPackagePath);

            if ($ours !== false && $theirs !== false && trim($ours) !== trim($theirs)) {
                $advisories[] = 'package.json';

                if ($collector !== null) {
                    $dependencyChanges = $this->dependencyChanges($ours, $theirs);
                    $dependencySummary = $dependencyChanges === []
                        ? 'the JavaScript dependency manifest'
                        : implode(', ', $dependencyChanges);

                    $collector->add(
                        'laravelUpgrade.packageJsonDependencies',
                        Finding::SEVERITY_INFO,
                        $this->targetMajor($toSkeletonDirectory),
                        'package.json',
                        0,
                        sprintf('The target Laravel skeleton changes JavaScript dependencies in package.json: %s.', $dependencySummary),
                        'Review and apply these changes with your package manager; package.json was not modified automatically.'
                    );
                }
            }
        }

        return ['changed' => $changed, 'conflicts' => $conflicts, 'advisories' => $advisories];
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

    private function write(string $path, string $contents, ?int $sourceMode = null): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && (! mkdir($directory, 0777, true) && ! is_dir($directory))) {
            throw new RuntimeException(sprintf('Could not create directory "%s".', $directory));
        }

        $temporaryPath = tempnam($directory, basename($path).'.tmp-');

        if ($temporaryPath === false) {
            throw new RuntimeException(sprintf('Could not create temporary file for "%s".', $path));
        }

        try {
            $written = file_put_contents($temporaryPath, $contents, LOCK_EX);

            if ($written !== strlen($contents)) {
                throw new RuntimeException(sprintf('Could not write non-PHP file "%s".', $path));
            }

            $mode = is_file($path) ? fileperms($path) : $sourceMode;

            if ($mode === false || $mode === null) {
                $mode = 0644;
            }

            if (! chmod($temporaryPath, $mode & 0777)) {
                throw new RuntimeException(sprintf('Could not set permissions for "%s".', $path));
            }

            if (! rename($temporaryPath, $path)) {
                throw new RuntimeException(sprintf('Could not replace non-PHP file "%s".', $path));
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function targetMajor(string $skeletonDirectory): int
    {
        $name = basename(rtrim($skeletonDirectory, '/'));

        return ctype_digit($name) ? (int) $name : 0;
    }

    private function preservePhpunitSchema(string $ours, string $theirs): string
    {
        if (preg_match('/https:\/\/schema\.phpunit\.de\/\d+(?:\.\d+)*\/phpunit\.xsd/', $ours, $match) !== 1) {
            return $theirs;
        }

        $updated = preg_replace(
            '/https:\/\/schema\.phpunit\.de\/\d+(?:\.\d+)*\/phpunit\.xsd/',
            $match[0],
            $theirs,
        );

        return is_string($updated) ? $updated : $theirs;
    }

    /**
     * @return list<string>
     */
    private function dependencyChanges(string $ours, string $theirs): array
    {
        $before = json_decode($ours, true);
        $after = json_decode($theirs, true);

        if (! is_array($before) || ! is_array($after)) {
            return [];
        }

        $changes = [];

        foreach (['dependencies', 'devDependencies'] as $section) {
            $beforeDependencies = is_array($before[$section] ?? null) ? $before[$section] : [];
            $afterDependencies = is_array($after[$section] ?? null) ? $after[$section] : [];
            $names = array_unique(array_merge(array_keys($beforeDependencies), array_keys($afterDependencies)));

            foreach ($names as $name) {
                if (! is_string($name) || ($beforeDependencies[$name] ?? null) === ($afterDependencies[$name] ?? null)) {
                    continue;
                }

                $changes[] = $name;
            }
        }

        sort($changes);

        return array_values(array_unique($changes));
    }

    /** @return list<string> */
    private function pathList(): array
    {
        return [];
    }

    private function detectPhpunitMajor(string $projectDirectory): ?int
    {
        foreach ([
            $projectDirectory.'/vendor/composer/installed.json',
            $projectDirectory.'/vendor/phpunit/phpunit/composer.json',
            $projectDirectory.'/composer.lock',
        ] as $path) {
            if (! is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            $decoded = json_decode($contents, true);

            if (! is_array($decoded)) {
                continue;
            }

            $packages = isset($decoded['packages']) || isset($decoded['packages-dev'])
                ? array_merge(
                    is_array($decoded['packages'] ?? null) ? $decoded['packages'] : [],
                    is_array($decoded['packages-dev'] ?? null) ? $decoded['packages-dev'] : [],
                )
                : [$decoded];

            // A package's installed composer.json is authoritative even when
            // composer.lock is stale. Its name is known from the path, while
            // installed.json/lock carry it explicitly.
            if ($path === $projectDirectory.'/vendor/phpunit/phpunit/composer.json') {
                $packages = [['name' => 'phpunit/phpunit'] + $decoded];
            }

            foreach ($packages as $package) {
                if (! is_array($package) || ($package['name'] ?? null) !== 'phpunit/phpunit') {
                    continue;
                }

                $version = $package['version'] ?? null;

                if (! is_string($version) || preg_match('/^v?(\d+)(?:\.\d+){0,2}(?:[-+].*)?$/', $version, $match) !== 1) {
                    continue;
                }

                return (int) $match[1];
            }
        }

        return null;
    }

    private function fileMode(string $path): ?int
    {
        $mode = fileperms($path);

        return $mode === false ? null : $mode;
    }
}
