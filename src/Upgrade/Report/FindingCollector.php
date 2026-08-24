<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report;

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
        string $guideUrl = ''
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
            guideUrl: $guideUrl
        );

        $this->findings[] = $finding;

        return $finding;
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
     * @param list<Finding> $findings
     */
    public function merge(array $findings): void
    {
        foreach ($findings as $finding) {
            $this->findings[] = $finding;
            ++$this->nextId;
        }
    }
}
