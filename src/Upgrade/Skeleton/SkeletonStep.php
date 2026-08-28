<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use RuntimeException;

/**
 * Synchronizes a project with the target Laravel skeleton.
 *
 * The short sync() API remains available for callers that only want config
 * reconciliation. syncProject() applies the complete Phase 5 file policy.
 */
final class SkeletonStep
{
    /**
     * Config files that exist in most Laravel skeletons. The repository may
     * contain fewer files for a lightweight/partial snapshot.
     *
     * @var list<string>
     */
    private const CONFIG_FILES = [
        'app', 'auth', 'broadcasting', 'cache', 'cors', 'database', 'filesystems',
        'hashing', 'logging', 'mail', 'queue', 'services', 'session', 'view',
    ];

    /** Files whose merge semantics belong to NonPhpFileMerger. */
    private const NON_PHP_FILES = [
        '.editorconfig', '.gitignore', 'package.json', 'phpunit.xml', 'vite.config.js',
    ];

    private ConfigArrayMerger $merger;

    private FileClassifier $classifier;

    private ThreeWayMerger $threeWayMerger;

    private SkeletonRepository $repository;

    public function __construct(
        ?SkeletonRepository $repository = null,
        ?ConfigArrayMerger $merger = null,
        ?FileClassifier $classifier = null,
        ?ThreeWayMerger $threeWayMerger = null
    ) {
        $this->repository = $repository ?? new SkeletonRepository;
        $this->merger = $merger ?? new ConfigArrayMerger;
        $this->classifier = $classifier ?? new FileClassifier;
        $this->threeWayMerger = $threeWayMerger ?? new ThreeWayMerger;
    }

    /**
     * Merges missing config keys from a target snapshot. This is kept
     * backwards-compatible with the original Phase 5 prototype.
     *
     * @return list<string>
     */
    public function sync(
        string $projectConfigDirectory,
        int $targetMajor,
        ?FindingCollector $collector = null,
        bool $dryRun = false
    ): array {
        if (! is_dir($projectConfigDirectory)) {
            return [];
        }

        $merged = [];

        foreach (self::CONFIG_FILES as $configName) {
            $projectPath = $projectConfigDirectory.'/'.$configName.'.php';
            $upstreamPath = $this->upstreamConfigPath($targetMajor, $configName);

            if (! is_file($projectPath) || $upstreamPath === null) {
                continue;
            }

            try {
                $result = $this->merger->merge(
                    $projectPath,
                    $upstreamPath,
                    $collector,
                    $targetMajor
                );
            } catch (RuntimeException) {
                continue;
            }

            $current = file_get_contents($projectPath);

            if ($current === false || $result === $current) {
                continue;
            }

            if (! $dryRun) {
                $this->writeFile($projectPath, $result);
            }

            $merged[] = $configName.'.php';
        }

        return $merged;
    }

    /**
     * Applies the full skeleton policy for one major transition.
     *
     * @return array{changed: list<string>, added: list<string>, removed: list<string>, modified: list<string>, renamed: array<string, string>, conflicts: list<string>}
     */
    public function syncProject(
        string $projectDirectory,
        int $fromMajor,
        int $targetMajor,
        ?FindingCollector $collector = null,
        bool $dryRun = false,
        string $structure = 'keep'
    ): array {
        $fromDirectory = $this->repository->path($fromMajor);
        $toDirectory = $this->repository->path($targetMajor);

        if (! is_dir($fromDirectory) || ! is_dir($toDirectory) || ! is_dir($projectDirectory)) {
            return [
                'changed' => [],
                'added' => [],
                'removed' => [],
                'modified' => [],
                'renamed' => [],
                'conflicts' => [],
            ];
        }

        // A partial snapshot is useful for config policies, but its missing
        // files do not mean that upstream deleted those files. Never run a
        // file classifier against one: it would manufacture removals and
        // additions from the intentionally sparse offline resources.
        if (! $this->repository->isComplete($fromMajor) || ! $this->repository->isComplete($targetMajor)) {
            $configChanges = $this->sync($projectDirectory.'/config', $targetMajor, $collector, $dryRun);

            if ($collector !== null) {
                $collector->add(
                    'laravelUpgrade.skeletonSyncSkipped',
                    Finding::SEVERITY_MEDIUM,
                    $targetMajor,
                    'resources/skeletons/MANIFEST.json',
                    0,
                    sprintf('Full Laravel %d skeleton synchronization was skipped because a snapshot is partial.', $targetMajor),
                    'Refresh complete snapshots with bin/build-skeletons before applying file-level skeleton changes.'
                );
            }

            return [
                'changed' => array_map(static fn (string $file): string => 'config/'.$file, $configChanges),
                'added' => [],
                'removed' => [],
                'modified' => [],
                'renamed' => [],
                'conflicts' => [],
            ];
        }

        $classification = $this->classifier->classify($fromDirectory, $toDirectory);
        $changed = [];
        $conflicts = [];

        foreach ($classification['added'] as $relative) {
            if ($this->isRoute($relative)) {
                $this->reportRouteChange($collector, $targetMajor, $relative, 'added');

                continue;
            }

            if ($this->excluded($relative) || $this->structureOnly($targetMajor, $relative)) {
                continue;
            }

            $targetPath = $projectDirectory.'/'.$relative;
            $sourcePath = $toDirectory.'/'.$relative;

            if ($this->isMigration($relative)) {
                if (! file_exists($targetPath) && $collector !== null) {
                    $collector->add(
                        'laravelUpgrade.skeletonMigrationAdded',
                        Finding::SEVERITY_MEDIUM,
                        $targetMajor,
                        $relative,
                        0,
                        sprintf('Laravel %d added migration "%s" to the application skeleton.', $targetMajor, $relative),
                        'Review the migration and publish or adapt it manually; it was not copied because it may collide with an existing table.',
                    );
                }

                continue;
            }

            if (file_exists($targetPath) || ! is_file($sourcePath)) {
                continue;
            }

            $contents = file_get_contents($sourcePath);

            if ($contents === false) {
                throw new RuntimeException(sprintf('Could not read skeleton file "%s".', $sourcePath));
            }

            $changed[] = $relative;

            if (! $dryRun) {
                $this->writeFile($targetPath, $contents, $this->fileMode($sourcePath));
            }
        }

        foreach ($classification['removed'] as $relative) {
            if ($this->isRoute($relative)) {
                $this->reportRouteChange($collector, $targetMajor, $relative, 'removed');

                continue;
            }

            if ($this->excluded($relative) || $structure === 'modern') {
                continue;
            }

            if (! is_file($projectDirectory.'/'.$relative)) {
                continue;
            }

            if ($collector !== null) {
                $collector->add(
                    'laravelUpgrade.skeletonFileRemoved',
                    Finding::SEVERITY_INFO,
                    $targetMajor,
                    $relative,
                    0,
                    sprintf('The Laravel %d skeleton removed "%s", but the project still contains it.', $targetMajor, $relative),
                    'Delete it only after confirming that your application no longer references or customizes it.'
                );
            }
        }

        foreach ($classification['modified'] as $relative) {
            if ($this->isRoute($relative)) {
                $this->reportRouteChange($collector, $targetMajor, $relative, 'modified');

                continue;
            }

            if ($this->excluded($relative)) {
                continue;
            }

            $oursPath = $projectDirectory.'/'.$relative;
            $basePath = $fromDirectory.'/'.$relative;
            $theirsPath = $toDirectory.'/'.$relative;

            if (! is_file($theirsPath)) {
                continue;
            }

            if (str_starts_with($relative, 'config/')) {
                $this->syncConfigFile(
                    $oursPath,
                    $theirsPath,
                    $basePath,
                    $relative,
                    $targetMajor,
                    $collector,
                    $changed,
                    $dryRun
                );

                continue;
            }

            if (! is_file($oursPath)) {
                $contents = file_get_contents($theirsPath);

                if ($contents === false) {
                    throw new RuntimeException(sprintf('Could not read skeleton file "%s".', $theirsPath));
                }

                $changed[] = $relative;

                if (! $dryRun) {
                    $this->writeFile($oursPath, $contents, $this->fileMode($theirsPath));
                }

                continue;
            }

            $ours = file_get_contents($oursPath);
            $base = is_file($basePath) ? file_get_contents($basePath) : '';
            $theirs = file_get_contents($theirsPath);

            if ($ours === false || $theirs === false || $base === false) {
                continue;
            }

            if ($ours === $base) {
                $merged = ['content' => $theirs, 'conflicted' => false];
            } else {
                $merged = $this->threeWayMerger->mergeWithStatus($ours, $base, $theirs);
            }

            if ($merged['content'] === $ours) {
                continue;
            }

            $changed[] = $relative;

            if ($merged['conflicted']) {
                $conflicts[] = $relative;

                if ($collector !== null) {
                    $collector->add(
                        'laravelUpgrade.skeletonMergeConflict',
                        Finding::SEVERITY_HIGH,
                        $targetMajor,
                        $relative,
                        0,
                        sprintf('Customized skeleton file "%s" has a merge conflict.', $relative),
                        'Resolve the generated .laravel-upgrade/merge file, then run continue.'
                    );
                }

                if (! $dryRun) {
                    $this->writeFile($projectDirectory.'/.laravel-upgrade/merge/'.$relative.'.merged', $merged['content']);
                }

                continue;
            }

            if (! $dryRun) {
                $this->writeFile($oursPath, $merged['content']);
            }
        }

        foreach ($classification['renamed'] as $old => $new) {
            if ($this->isRoute($old) || $this->isRoute($new)) {
                $this->reportRouteChange(
                    $collector,
                    $targetMajor,
                    $this->isRoute($new) ? $new : $old,
                    'renamed',
                );

                continue;
            }

            if ($this->excluded($old) || $this->excluded($new) || $structure === 'modern') {
                continue;
            }

            // A migration filename change is not a safe rename operation:
            // migrations are application history and copying or deleting one
            // can collide with a table that already exists. Keep the old
            // project file and surface the new upstream migration instead.
            if ($this->isMigration($new)) {
                if (! file_exists($projectDirectory.'/'.$new) && $collector !== null) {
                    $collector->add(
                        'laravelUpgrade.skeletonMigrationAdded',
                        Finding::SEVERITY_MEDIUM,
                        $targetMajor,
                        $new,
                        0,
                        sprintf('Laravel %d changed migration "%s" in the application skeleton.', $targetMajor, $new),
                        'Review the migration and publish or adapt it manually; it was not renamed automatically because migrations are application history.',
                    );
                }

                continue;
            }

            $oldPath = $projectDirectory.'/'.$old;
            $newPath = $projectDirectory.'/'.$new;

            if (! is_file($oldPath) || is_file($newPath)) {
                continue;
            }

            $changed[] = $new;

            $contents = file_get_contents($oldPath);

            if ($contents === false) {
                throw new RuntimeException(sprintf('Could not read project file "%s".', $oldPath));
            }

            if (! $dryRun) {
                $this->writeFile($newPath, $contents, $this->fileMode($oldPath));

                if (! unlink($oldPath)) {
                    throw new RuntimeException(sprintf('Could not remove renamed project file "%s".', $oldPath));
                }
            }
        }

        // Config files can be identical in the skeletons but still need
        // policy reconciliation when the project has custom values.
        foreach (self::CONFIG_FILES as $configName) {
            $projectPath = $projectDirectory.'/config/'.$configName.'.php';
            $upstreamPath = $toDirectory.'/config/'.$configName.'.php';

            if (! is_file($projectPath) || ! is_file($upstreamPath)) {
                continue;
            }

            if (! in_array('config/'.$configName.'.php', $classification['modified'], true)) {
                $this->syncConfigFile(
                    $projectPath,
                    $upstreamPath,
                    $fromDirectory.'/config/'.$configName.'.php',
                    'config/'.$configName.'.php',
                    $targetMajor,
                    $collector,
                    $changed,
                    $dryRun
                );
            }
        }

        // These files are safe to synchronize only after both snapshot sides
        // have provenance marked complete. EnvExampleMerger never receives
        // or writes the real .env file.
        $envExamplePath = $projectDirectory.'/.env.example';
        $upstreamEnvExamplePath = $toDirectory.'/.env.example';

        if (is_file($envExamplePath) && is_file($upstreamEnvExamplePath)) {
            $envMerger = new EnvExampleMerger;
            $current = file_get_contents($envExamplePath);

            if ($current !== false) {
                try {
                    $mergedEnv = $envMerger->merge(
                        $envExamplePath,
                        $targetMajor,
                        $upstreamEnvExamplePath,
                        $collector
                    );

                    if ($mergedEnv !== $current) {
                        $changed[] = '.env.example';

                        if (! $dryRun) {
                            $this->writeFile($envExamplePath, $mergedEnv);
                        }
                    }
                } catch (RuntimeException) {
                    // A malformed example is reported by the advisory pass;
                    // it must not prevent code/config synchronization.
                }
            }
        }

        $nonPhp = (new NonPhpFileMerger)->sync(
            $projectDirectory,
            $fromDirectory,
            $toDirectory,
            $collector,
            $dryRun
        );
        $changed = array_merge($changed, $nonPhp['changed']);
        $conflicts = array_merge($conflicts, $nonPhp['conflicts']);

        $added = array_values(array_filter(
            $classification['added'],
            fn (string $relative): bool => ! $this->nonPhpFile($relative),
        ));
        $removed = array_values(array_filter(
            $classification['removed'],
            fn (string $relative): bool => ! $this->nonPhpFile($relative),
        ));
        $modified = array_values(array_filter(
            $classification['modified'],
            fn (string $relative): bool => ! $this->nonPhpFile($relative),
        ));
        $renamed = array_filter(
            $classification['renamed'],
            fn (string $new, string $old): bool => ! $this->nonPhpFile($old) && ! $this->nonPhpFile($new),
            ARRAY_FILTER_USE_BOTH,
        );

        return [
            'changed' => array_values(array_unique($changed)),
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
            'renamed' => $renamed,
            'conflicts' => array_values(array_unique($conflicts)),
        ];
    }

    public function upstreamConfigPath(int $targetMajor, string $configName): ?string
    {
        $path = $this->repository->path($targetMajor).'/config/'.$configName.'.php';

        return is_file($path) ? $path : null;
    }

    /** @param list<string> $changed */
    private function syncConfigFile(
        string $projectPath,
        string $upstreamPath,
        ?string $basePath,
        string $relative,
        int $targetMajor,
        ?FindingCollector $collector,
        array &$changed,
        bool $dryRun
    ): void {
        try {
            $result = $basePath !== null && is_file($basePath)
                ? $this->merger->mergeWithBase($projectPath, $basePath, $upstreamPath, $collector, $targetMajor)
                : $this->merger->merge($projectPath, $upstreamPath, $collector, $targetMajor);
        } catch (RuntimeException) {
            return;
        }

        $current = file_get_contents($projectPath);

        if ($current === false || $current === $result) {
            return;
        }

        $changed[] = $relative;

        if (! $dryRun) {
            $this->writeFile($projectPath, $result);
        }
    }

    private function excluded(string $relative): bool
    {
        if ($relative === '.env' || $relative === '.env.example'
            || $relative === 'composer.json' || $relative === 'composer.lock'
            || $relative === 'package-lock.json' || $this->nonPhpFile($relative)
            || str_starts_with($relative, 'app/')) {
            return true;
        }

        if (str_starts_with($relative, 'routes/')) {
            return true;
        }

        return str_starts_with($relative, 'tests/') && $relative !== 'tests/TestCase.php';
    }

    private function nonPhpFile(string $relative): bool
    {
        return in_array($relative, self::NON_PHP_FILES, true);
    }

    private function structureOnly(int $targetMajor, string $relative): bool
    {
        try {
            $metadata = $this->repository->metadata($targetMajor);
        } catch (RuntimeException) {
            $metadata = [];
        }

        $paths = $metadata['structureOnly'] ?? $metadata['structure-only'] ?? [];

        $policyPath = dirname(__DIR__, 3).'/resources/config-policies/'.$targetMajor.'.json';
        $policyContents = is_file($policyPath) ? file_get_contents($policyPath) : false;
        $policy = is_string($policyContents) ? json_decode($policyContents, true) : null;

        if (is_array($policy) && is_array($policy['structureOnly'] ?? null)) {
            $paths = array_merge(is_array($paths) ? $paths : [], $policy['structureOnly']);
        }

        if (! is_array($paths)) {
            return false;
        }

        return in_array($relative, $paths, true);
    }

    private function isMigration(string $relative): bool
    {
        return str_starts_with($relative, 'database/migrations/');
    }

    private function isRoute(string $relative): bool
    {
        return str_starts_with($relative, 'routes/');
    }

    private function reportRouteChange(
        ?FindingCollector $collector,
        int $targetMajor,
        string $relative,
        string $change,
    ): void {
        if ($collector === null) {
            return;
        }

        $collector->add(
            'laravelUpgrade.skeletonRouteChanged',
            Finding::SEVERITY_INFO,
            $targetMajor,
            $relative,
            0,
            sprintf('The Laravel %d skeleton %s route file "%s".', $targetMajor, $change, $relative),
            'Review the route changes manually; route files are application code and were not modified automatically.',
        );
    }

    private function writeFile(string $path, string $contents, ?int $sourceMode = null): void
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
                throw new RuntimeException(sprintf('Could not write skeleton file "%s".', $path));
            }

            $mode = is_file($path) ? fileperms($path) : $sourceMode;

            if ($mode === false || $mode === null) {
                $mode = 0644;
            }

            if (! chmod($temporaryPath, $mode & 0777)) {
                throw new RuntimeException(sprintf('Could not set permissions for "%s".', $path));
            }

            if (! rename($temporaryPath, $path)) {
                throw new RuntimeException(sprintf('Could not replace skeleton file "%s".', $path));
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function fileMode(string $path): ?int
    {
        $mode = fileperms($path);

        return $mode === false ? null : $mode & 0777;
    }
}
