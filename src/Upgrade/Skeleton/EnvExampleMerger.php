<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use RuntimeException;

/**
 * Adds new environment variable keys to .env.example for the target major
 * (plan P5-04). Never removes existing keys; flags renamed keys as findings.
 */
final class EnvExampleMerger
{
    /**
     * Environment variables introduced per major version.
     *
     * @var array<int, list<array{key: string, value: string}>>
     */
    private const NEW_KEYS = [
        11 => [
            ['key' => 'LOG_DEPRECATIONS_CHANNEL', 'value' => 'null'],
            ['key' => 'LOG_TRACE', 'value' => 'false'],
        ],
        12 => [],
        13 => [
            ['key' => 'CACHE_STORE', 'value' => 'database'],
        ],
    ];

    public function merge(string $envExamplePath, int $targetMajor): string
    {
        if (! is_file($envExamplePath)) {
            throw new RuntimeException(sprintf('"%s" was not found.', $envExamplePath));
        }

        $contents = file_get_contents($envExamplePath);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Could not read "%s".', $envExamplePath));
        }

        $newKeys = self::NEW_KEYS[$targetMajor] ?? [];

        if ($newKeys === []) {
            return $contents;
        }

        $lines = explode("\n", $contents);
        $existingKeys = [];

        foreach ($lines as $line) {
            if (preg_match('/^([A-Z_]+)=/', $line, $m) === 1) {
                $existingKeys[] = $m[1];
            }
        }

        $appends = [];

        foreach ($newKeys as $entry) {
            if (in_array($entry['key'], $existingKeys, true)) {
                continue;
            }

            $appends[] = sprintf('%s=%s', $entry['key'], $entry['value']);
        }

        if ($appends === []) {
            return $contents;
        }

        return rtrim($contents) . "\n\n# Laravel " . $targetMajor . "\n" . implode("\n", $appends) . "\n";
    }
}
