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
        ?FindingCollector $collector = null,
        ?string $baseExamplePath = null,
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
        $base = $baseExamplePath !== null && is_file($baseExamplePath)
            ? file_get_contents($baseExamplePath)
            : false;
        $baseKeys = $base !== false ? array_keys($this->parseAssignments($base)) : [];

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

            // Complete base snapshots are authoritative about keys that were
            // already available before this transition. Do not resurrect a
            // key that the project deliberately removed.
            if ($base !== false && in_array($key, $baseKeys, true)) {
                continue;
            }

            $appends[] = $entry['line'] ?? sprintf('%s=%s', $key, $entry['value']);
            $existingKeys[] = $key;
        }

        if ($appends === []) {
            return $contents;
        }

        if ($upstream !== false && $base !== false) {
            return $this->insertWithTargetAnchors($contents, (string) $upstream, $appends);
        }

        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $hadTrailingNewline = str_ends_with($contents, "\n");
        $body = rtrim($contents, "\r\n");
        $result = $body.$eol.$eol.'# Laravel '.$targetMajor.$eol.implode($eol, $appends);

        return $hadTrailingNewline ? $result.$eol : $result;
    }

    /**
     * Lists keys documented by .env.example that are missing from .env. It
     * intentionally accepts paths rather than values so callers can report
     * the missing runtime configuration without exposing secrets.
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

        return $this->missingFromEnvironmentContents($environment, $example);
    }

    /**
     * Content variant used by dry-run callers, so the proposed merged example
     * can be checked without writing it to the project.
     *
     * @return list<string>
     */
    public function missingFromEnvironmentContents(string $environment, string $example): array
    {
        $environmentKeys = array_keys($this->parseAssignments($environment));
        $exampleKeys = array_keys($this->parseAssignments($example));

        return array_values(array_diff($exampleKeys, $environmentKeys));
    }

    /**
     * @return list<array{key: string, value: string, line: string}>
     */
    private function entries(string $contents): array
    {
        $entries = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*(?:#\s*)?(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=\s*(.*)\s*$/', $line, $matches) !== 1) {
                continue;
            }

            $entries[] = ['key' => $matches[1], 'value' => $matches[2], 'line' => $line];
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
            if (preg_match('/^\s*(?:#\s*)?(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=\s*(.*)\s*$/', $line, $matches) === 1) {
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

    /**
     * Inserts complete-snapshot additions at positions derived from the
     * target's existing assignment anchors. Only genuinely new assignment
     * lines are copied; comments and blank separators in their target groups
     * travel with the inserted run.
     *
     * @param  list<string>  $appends
     */
    private function insertWithTargetAnchors(string $contents, string $target, array $appends): string
    {
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $hadTrailingNewline = str_ends_with($contents, "\n");
        $projectLines = $this->lines($contents);
        $targetLines = $this->lines($target);
        $appendKeys = [];

        foreach ($appends as $line) {
            $key = $this->assignmentKey($line);

            if ($key !== null) {
                $appendKeys[$key] = true;
            }
        }

        $projectKeys = array_keys($this->parseAssignments($contents));
        $targetAssignments = [];

        foreach ($targetLines as $index => $line) {
            $key = $this->assignmentKey($line);

            if ($key !== null) {
                $targetAssignments[] = ['index' => $index, 'key' => $key];
            }
        }

        $segments = [];
        $segmentStart = 0;
        $previousAnchor = null;
        $hasCandidate = false;

        foreach ($targetAssignments as $assignment) {
            if (in_array($assignment['key'], $projectKeys, true)) {
                $segment = $this->targetSegment(
                    $targetLines,
                    $segmentStart,
                    $assignment['index'],
                    $appendKeys,
                );

                if ($segment !== []) {
                    $segments[] = [
                        'lines' => $segment,
                        'before' => $previousAnchor === null,
                        'anchor' => $previousAnchor ?? $assignment['key'],
                    ];
                    $hasCandidate = true;
                }

                $previousAnchor = $assignment['key'];
                $segmentStart = $assignment['index'] + 1;
            }
        }

        $segment = $this->targetSegment($targetLines, $segmentStart, count($targetLines), $appendKeys);

        if ($segment !== []) {
            $segments[] = [
                'lines' => $segment,
                'before' => false,
                'anchor' => $previousAnchor,
            ];
            $hasCandidate = true;
        }

        if (! $hasCandidate) {
            return $contents;
        }

        foreach ($segments as $segment) {
            /** @var list<string> $segmentLines */
            $segmentLines = $segment['lines'];
            /** @var string|null $anchor */
            $anchor = $segment['anchor'];
            $projectIndex = $anchor === null ? null : $this->lineIndexForKey($projectLines, $anchor);

            if ($projectIndex === null) {
                $projectIndex = count($projectLines);
            } elseif (! $segment['before']) {
                $projectIndex++;
            }

            array_splice($projectLines, $projectIndex, 0, $segmentLines);
        }

        $result = implode($eol, $projectLines);

        return $hadTrailingNewline ? $result.$eol : $result;
    }

    /**
     * @param  list<string>  $lines
     * @param  array<string, true>  $appendKeys
     * @return list<string>
     */
    private function targetSegment(array $lines, int $start, int $end, array $appendKeys): array
    {
        $segment = [];
        $hasCandidate = false;

        for ($index = $start; $index < $end; $index++) {
            $line = $lines[$index];
            $key = $this->assignmentKey($line);

            if ($key !== null && ! isset($appendKeys[$key])) {
                continue;
            }

            if ($key !== null) {
                $hasCandidate = true;
            }

            $segment[] = $line;
        }

        return $hasCandidate ? $segment : [];
    }

    /** @return list<string> */
    private function lines(string $contents): array
    {
        $lines = preg_split('/\R/', $contents) ?: [];

        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    private function assignmentKey(string $line): ?string
    {
        if (preg_match('/^\s*(?:#\s*)?(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=/', $line, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /** @param list<string> $lines */
    private function lineIndexForKey(array $lines, string $key): ?int
    {
        foreach ($lines as $index => $line) {
            if ($this->assignmentKey($line) === $key) {
                return $index;
            }
        }

        return null;
    }
}
