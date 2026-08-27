<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report;

use JsonException;

/**
 * In-memory finding collector shared across upgrade steps (plan P3-01).
 * Serialised to JSONL at .laravel-upgrade/findings.jsonl for cross-process use.
 */
final class FindingCollector
{
    /**
     * @var list<Finding>
     */
    private array $findings = [];

    private int $nextId = 1;

    public function add(
        string $ruleId,
        string $severity,
        int $laravelVersion,
        string $file,
        int $line,
        string $message,
        string $action = '',
        string $guideUrl = '',
        bool $autoFixed = false,
        string $confidence = 'high',
    ): Finding {
        $finding = new Finding(
            id: sprintf('f-%04d', $this->nextId++),
            ruleId: $ruleId,
            severity: $severity,
            laravelVersion: $laravelVersion,
            file: $file,
            line: $line,
            message: $message,
            action: $action,
            guideUrl: $guideUrl,
            autoFixed: $autoFixed,
            confidence: $confidence,
        );

        $this->findings[] = $finding;

        return $finding;
    }

    public function addFinding(Finding $finding): Finding
    {
        return $this->add(
            ruleId: $finding->ruleId,
            severity: $finding->severity,
            laravelVersion: $finding->laravelVersion,
            file: $finding->file,
            line: $finding->line,
            message: $finding->message,
            action: $finding->action,
            guideUrl: $finding->guideUrl,
            autoFixed: $finding->autoFixed,
            confidence: $finding->confidence,
        );
    }

    /**
     * @return list<Finding>
     */
    public function all(): array
    {
        return $this->findings;
    }

    /**
     * @return list<Finding>
     */
    public function bySeverity(string $severity): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (Finding $f): bool => $f->severity === $severity
        ));
    }

    public function count(): int
    {
        return count($this->findings);
    }

    /**
     * @return array<string, int>
     */
    public function countBySeverity(): array
    {
        $counts = [];

        foreach ($this->findings as $finding) {
            $counts[$finding->severity] = ($counts[$finding->severity] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  list<Finding>  $findings
     */
    public function merge(array $findings): void
    {
        foreach ($findings as $finding) {
            $this->addFinding($finding);
        }
    }

    public function writeJsonl(string $path): void
    {
        $findings = $this->deduplicate(array_merge($this->readJsonl($path), $this->findings));
        $findings = $this->ensureUniqueIds($findings);
        $contents = '';

        foreach ($findings as $finding) {
            $contents .= json_encode($finding->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not write findings JSONL file "%s": directory does not exist.', $path));
        }

        $temporaryPath = tempnam($directory, basename($path).'.tmp-');

        if ($temporaryPath === false) {
            throw new \RuntimeException(sprintf('Could not create a temporary findings JSONL file for "%s".', $path));
        }

        try {
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false || ! rename($temporaryPath, $path)) {
                throw new \RuntimeException(sprintf('Could not write findings JSONL file "%s".', $path));
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * @return list<Finding>
     */
    private function readJsonl(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new \RuntimeException(sprintf('Could not read findings JSONL file "%s".', $path));
        }

        $findings = [];

        foreach ($lines as $lineNumber => $line) {
            if (trim($line) === '') {
                continue;
            }

            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new \RuntimeException(sprintf('Invalid finding JSON on line %d of "%s".', $lineNumber + 1, $path), previous: $exception);
            }

            if (! is_array($decoded)) {
                throw new \RuntimeException(sprintf('Finding JSON on line %d of "%s" must be an object.', $lineNumber + 1, $path));
            }

            /** @var array<string, mixed> $decoded */
            $findings[] = Finding::fromArray($decoded);
        }

        return $findings;
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<Finding>
     */
    private function deduplicate(array $findings): array
    {
        $unique = [];
        $seen = [];

        foreach ($findings as $finding) {
            $key = implode("\0", [
                $finding->ruleId,
                $finding->file,
                (string) $finding->line,
                $finding->message,
            ]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $finding;
        }

        return $unique;
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<Finding>
     */
    private function ensureUniqueIds(array $findings): array
    {
        $unique = [];
        $used = [];
        $nextId = 1;

        foreach ($findings as $finding) {
            $id = $finding->id;

            if ($id !== '' && ! isset($used[$id])) {
                $used[$id] = true;

                if (preg_match('/^f-(\d+)$/', $id, $match) === 1) {
                    $nextId = max($nextId, (int) $match[1] + 1);
                }

                $unique[] = $finding;

                continue;
            }

            do {
                $id = sprintf('f-%04d', $nextId++);
            } while (isset($used[$id]));

            $used[$id] = true;
            $unique[] = $this->withId($finding, $id);
        }

        return $unique;
    }

    private function withId(Finding $finding, string $id): Finding
    {
        return new Finding(
            id: $id,
            ruleId: $finding->ruleId,
            severity: $finding->severity,
            laravelVersion: $finding->laravelVersion,
            file: $finding->file,
            line: $finding->line,
            message: $finding->message,
            action: $finding->action,
            guideUrl: $finding->guideUrl,
            autoFixed: $finding->autoFixed,
            confidence: $finding->confidence,
        );
    }
}
