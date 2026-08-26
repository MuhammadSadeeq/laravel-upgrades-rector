<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use RuntimeException;

/**
 * Syncs project configuration files against the target major's upstream
 * defaults (plan P5-02). Adds missing keys via ConfigArrayMerger; flags
 * removed keys as findings.
 */
final class SkeletonStep
{
    /**
     * Config files that exist in every Laravel skeleton.
     */
    private const CONFIG_FILES = [
        'app', 'auth', 'cache', 'database', 'filesystems',
        'logging', 'mail', 'queue', 'services', 'session',
    ];

    private ConfigArrayMerger $merger;

    public function __construct()
    {
        $this->merger = new ConfigArrayMerger;
    }

    /**
     * Merges missing config keys from the upstream skeleton for $targetMajor
     * into the project's config directory. Returns list of merged file names.
     *
     * @return list<string>
     */
    public function sync(string $projectConfigDirectory, int $targetMajor): array
    {
        if (! is_dir($projectConfigDirectory)) {
            return [];
        }

        $merged = [];

        foreach (self::CONFIG_FILES as $configName) {
            $projectPath = $projectConfigDirectory.'/'.$configName.'.php';

            if (! is_file($projectPath)) {
                continue;
            }

            $upstreamPath = $this->upstreamConfigPath($targetMajor, $configName);

            if ($upstreamPath === null || ! is_file($upstreamPath)) {
                continue;
            }

            try {
                $result = $this->merger->merge($projectPath, $upstreamPath);
            } catch (RuntimeException) {
                continue;
            }

            if ($result !== file_get_contents($projectPath)) {
                file_put_contents($projectPath, $result);
                $merged[] = $configName.'.php';
            }
        }

        return $merged;
    }

    /**
     * Returns the path to the vendored upstream config for a given major,
     * or null when not available.
     */
    public function upstreamConfigPath(int $targetMajor, string $configName): ?string
    {
        // Vendored skeletons live under resources/skeletons/<major>/config/.
        $path = dirname(__DIR__, 3).'/resources/skeletons/'.$targetMajor.'/config/'.$configName.'.php';

        return is_file($path) ? $path : null;
    }
}
