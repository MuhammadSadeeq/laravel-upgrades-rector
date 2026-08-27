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

    /** @var list<string> */
    private const EXCLUDED_PREFIXES = [
        '.env',
        'composer.json',
        'composer.lock',
        'package-lock.json',
        'app/',
        'routes/',
        'tests/',
        'public/index.php',
        'artisan',
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
                file_put_contents($projectPath, $result);
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
            if ($this->excluded($relative) || $this->structureOnly($targetMajor, $relative)) {
                continue;
            }

            $targetPath = $projectDirectory.'/'.$relative;
            $sourcePath = $toDirectory.'/'.$relative;

            if (is_file($targetPath) || ! is_file($sourcePath)) {
                continue;
            }

            $changed[] = $relative;

            if (! $dryRun) {
                $this->writeFile($targetPath, (string) file_get_contents($sourcePath));
            }
        }

        foreach ($classification['removed'] as $relative) {
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
                    sprintf('The Laravel %d skeleton removed "%s".', $targetMajor, $relative),
                    'Delete it only after confirming that your application no longer references or customizes it.'
                );
            }
        }

        foreach ($classification['modified'] as $relative) {
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
                    $relative,
                    $targetMajor,
                    $collector,
                    $changed,
                    $dryRun
                );

                continue;
            }

            if (! is_file($oursPath)) {
                $changed[] = $relative;

                if (! $dryRun) {
                    $this->writeFile($oursPath, (string) file_get_contents($theirsPath));
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
            if ($this->excluded($old) || $this->excluded($new) || $structure === 'modern') {
                continue;
            }

            $oldPath = $projectDirectory.'/'.$old;
            $newPath = $projectDirectory.'/'.$new;

            if (! is_file($oldPath) || is_file($newPath)) {
                continue;
            }

            $changed[] = $new;

            if (! $dryRun) {
                $this->writeFile($newPath, (string) file_get_contents($oldPath));
                unlink($oldPath);
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

        return [
            'changed' => array_values(array_unique($changed)),
            'added' => $classification['added'],
            'removed' => $classification['removed'],
            'modified' => $classification['modified'],
            'renamed' => $classification['renamed'],
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
        string $relative,
        int $targetMajor,
        ?FindingCollector $collector,
        array &$changed,
        bool $dryRun
    ): void {
        try {
            $result = $this->merger->merge($projectPath, $upstreamPath, $collector, $targetMajor);
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
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($relative === $prefix || str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function structureOnly(int $targetMajor, string $relative): bool
    {
        try {
            $metadata = $this->repository->metadata($targetMajor);
        } catch (RuntimeException) {
            return false;
        }

        $paths = $metadata['structureOnly'] ?? $metadata['structure-only'] ?? [];

        if (! is_array($paths)) {
            return false;
        }

        return in_array($relative, $paths, true);
    }

    private function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $contents);
    }
}
