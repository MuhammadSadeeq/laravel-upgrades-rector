<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use RuntimeException;

/**
 * Synchronizes .env.example without ever touching the real .env file.
 */
final class EnvExampleMerger
{
    /**
     * Fallback values used when a complete skeleton snapshot is unavailable.
     * A full snapshot, when supplied to merge(), always takes precedence.
     *
     * @var array<int, list<array{key: string, value: string}>>
     */
    private const NEW_KEYS = [
        11 => [
            ['key' => 'LOG_DEPRECATIONS_CHANNEL', 'value' => 'null'],
            ['key' => 'LOG_TRACE', 'value' => 'false'],
            ['key' => 'CACHE_STORE', 'value' => 'database'],
        ],
        12 => [],
        13 => [
            ['key' => 'CACHE_STORE', 'value' => 'database'],
        ],
    ];

    /**
     * Names renamed by the slim skeleton. These are findings, not automatic
     * edits: an application may intentionally keep the old config shape.
     *
     * @var array<int, array<string, string>>
     */
    private const RENAMED_KEYS = [
        11 => [
            'CACHE_DRIVER' => 'CACHE_STORE',
            'BROADCAST_DRIVER' => 'BROADCAST_CONNECTION',
        ],
        12 => [],
        13 => [],
    ];

    /**
     * @throws RuntimeException when the example file is missing or unreadable
     */
    public function merge(
        string $envExamplePath,
        int $targetMajor,
        ?string $upstreamExamplePath = null,
        ?FindingCollector $collector = null
    ): string {
        if (! is_file($envExamplePath)) {
            throw new RuntimeException(sprintf('"%s" was not found.', $envExamplePath));
        }

        $contents = file_get_contents($envExamplePath);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Could not read "%s".', $envExamplePath));
        }

        $upstream = $upstreamExamplePath !== null && is_file($upstreamExamplePath)
            ? file_get_contents($upstreamExamplePath)
            : false;
        $entries = $upstream !== false
            ? $this->entries((string) $upstream)
            : self::NEW_KEYS[$targetMajor] ?? [];
        $existingKeys = array_keys($this->parseAssignments($contents));

        if ($collector !== null) {
            foreach (self::RENAMED_KEYS[$targetMajor] ?? [] as $old => $new) {
                if (in_array($old, $existingKeys, true) && ! in_array($new, $existingKeys, true)) {
                    $collector->add(
                        'laravelUpgrade.envKeyRenamed',
                        Finding::SEVERITY_MEDIUM,
                        $targetMajor,
                        '.env.example',
                        $this->lineForKey($contents, $old),
                        sprintf('Laravel %d renamed the environment key %s to %s.', $targetMajor, $old, $new),
                        sprintf('Update the application configuration and .env from %s to %s when adopting the slim skeleton.', $old, $new)
                    );
                }
            }
        }

        $appends = [];

        foreach ($entries as $entry) {
            $key = $entry['key'];

            if (in_array($key, $existingKeys, true)) {
                continue;
            }

            $appends[] = sprintf('%s=%s', $key, $entry['value']);
            $existingKeys[] = $key;
        }

        if ($appends === []) {
            return $contents;
        }

        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $hadTrailingNewline = str_ends_with($contents, "\n");
        $body = rtrim($contents, "\r\n");
        $result = $body.$eol.$eol.'# Laravel '.$targetMajor.$eol.implode($eol, $appends);

        return $hadTrailingNewline ? $result.$eol : $result;
    }

    /**
     * Lists keys in .env that are missing from .env.example. It intentionally
     * accepts paths rather than values so callers can report both files.
     *
     * @return list<string>
     */
    public function missingFromEnvironment(string $envPath, string $envExamplePath): array
    {
        if (! is_file($envPath) || ! is_file($envExamplePath)) {
            return [];
        }

        $environment = file_get_contents($envPath);
        $example = file_get_contents($envExamplePath);

        if ($environment === false || $example === false) {
            return [];
        }

        $environmentKeys = array_keys($this->parseAssignments($environment));
        $exampleKeys = array_keys($this->parseAssignments($example));

        return array_values(array_diff($environmentKeys, $exampleKeys));
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function entries(string $contents): array
    {
        $entries = [];

        foreach ($this->parseAssignments($contents) as $key => $value) {
            $entries[] = ['key' => $key, 'value' => $value];
        }

        return $entries;
    }

    /**
     * Parse only assignment lines. Comments, export syntax and quoted values
     * remain untouched in the source being merged.
     *
     * @return array<string, string>
     */
    private function parseAssignments(string $contents): array
    {
        $assignments = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=\s*(.*)\s*$/', $line, $matches) === 1) {
                $assignments[$matches[1]] = $matches[2];
            }
        }

        return $assignments;
    }

    private function lineForKey(string $contents, string $key): int
    {
        foreach (preg_split('/\R/', $contents) ?: [] as $index => $line) {
            if (preg_match('/^\s*(?:export\s+)?'.preg_quote($key, '/').'\s*=/', $line) === 1) {
                return $index + 1;
            }
        }

        return 0;
    }
}
