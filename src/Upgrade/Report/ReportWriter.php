<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report;

use MuhammadSadeeq\LaravelUpgradesRector\PackageInfo;

/**
 * Renders the versioned upgrade report document.
 *
 * The writer only renders data. Atomic persistence is owned by the report
 * generator so callers can update the canonical JSON and Markdown files
 * without leaving a partially written report behind.
 */
final class ReportWriter
{
    /**
     * Backwards-compatible facade for callers that already have findings and
     * project metadata but not a complete report document.
     *
     * @param  list<Finding>  $findings
     * @param  array<string, mixed>  $project
     */
    public function writeMarkdown(array $findings, array $project, string $targetPath): void
    {
        $this->writeMarkdownReport([
            'project' => $project,
            'findings' => array_map(
                static fn (Finding $finding): array => $finding->toArray(),
                $findings,
            ),
            'steps' => [],
            'dependencies' => [],
            'codeChanges' => [],
            'skeleton' => [],
            'verification' => [],
            'whatToolDidNotDo' => [],
        ], $targetPath);
    }

    /**
     * Render the required report sections in their canonical order.
     *
     * @param  array<string, mixed>  $report
     */
    public function writeMarkdownReport(array $report, string $targetPath): void
    {
        $project = $this->arrayValue($report, 'project');
        $findings = $this->findingArrays($this->arrayValue($report, 'findings'));
        $high = $this->findingsBySeverity($findings, Finding::SEVERITY_HIGH);
        $medium = $this->findingsBySeverity($findings, Finding::SEVERITY_MEDIUM);
        $low = $this->findingsBySeverity($findings, Finding::SEVERITY_LOW);
        $info = $this->findingsBySeverity($findings, Finding::SEVERITY_INFO);
        $steps = $this->arrayValue($report, 'steps');
        $lastStep = $steps[count($steps) - 1] ?? null;
        $lastStepName = is_array($lastStep) && is_string($lastStep['name'] ?? null)
            ? $lastStep['name']
            : 'unknown';

        $markdown = "# Laravel Upgrade Report\n\n";
        $markdown .= "## Summary\n\n";
        $markdown .= sprintf(
            "- From: %s\n- To: %s\n- PHP: %s\n- Composer: %s\n- Commits: %s\n- Duration: %s\n- Last step: %s\n\n",
            $this->scalar($project['from'] ?? '?'),
            $this->scalar($project['to'] ?? '?'),
            $this->scalar($project['php'] ?? '?'),
            $this->scalar($project['composer'] ?? 'unknown'),
            $this->scalar($project['commits'] ?? 'unknown'),
            $this->scalar($project['duration'] ?? 'unknown'),
            $lastStepName,
        );
        $markdown .= "| Severity | Count |\n|---|---:|\n";
        $markdown .= sprintf("| High | %d |\n| Medium | %d |\n| Low | %d |\n| Info | %d |\n\n", count($high), count($medium), count($low), count($info));

        $markdown .= "## Manual actions\n\n";

        if ($high === []) {
            $markdown .= "None identified.\n\n";
        } else {
            foreach ($high as $finding) {
                $markdown .= $this->findingLine($finding, true);
            }

            $markdown .= "\n";
        }

        $markdown .= "## Dependencies\n\n";
        $dependencies = $this->arrayValue($report, 'dependencies');

        if ($dependencies === []) {
            $markdown .= "No dependency decisions were recorded.\n\n";
        } else {
            $markdown .= "| Package | Section | From | To | Reason | Installed |\n|---|---|---|---|---|---|\n";

            foreach ($dependencies as $dependency) {
                if (! is_array($dependency)) {
                    continue;
                }

                $markdown .= sprintf(
                    "| %s | %s | %s | %s | %s | %s |\n",
                    $this->cell($dependency['package'] ?? 'unknown'),
                    $this->cell($dependency['section'] ?? 'unknown'),
                    $this->cell($dependency['from'] ?? 'unknown'),
                    $this->cell($dependency['to'] ?? ($dependency['proposed'] ?? 'unknown')),
                    $this->cell($dependency['reason'] ?? 'unknown — verify'),
                    $this->cell($dependency['installed'] ?? 'unknown'),
                );
            }

            $markdown .= "\n";
        }

        $markdown .= "## Code changes\n\n";
        $codeChanges = $this->arrayValue($report, 'codeChanges');

        if ($codeChanges === []) {
            $markdown .= "No code changes were recorded.\n\n";
        } else {
            foreach ($codeChanges as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $markdown .= sprintf(
                    "- **%s** (%s): %s\n",
                    $this->scalar($change['transition'] ?? $change['major'] ?? 'unknown'),
                    $this->scalar($change['status'] ?? 'unknown'),
                    $this->scalar($change['message'] ?? 'Code step recorded.'),
                );
                $files = $this->stringList($change['changedFiles'] ?? []);

                if ($files !== []) {
                    $markdown .= '  - Files: '.implode(', ', array_map([$this, 'code'], $files))."\n";
                }

                $rules = $change['appliedRuleCounts'] ?? $change['appliedRules'] ?? [];

                if (is_array($rules) && $rules !== []) {
                    $markdown .= '  - Rules: '.$this->formatRuleCounts($rules)."\n";
                }
            }

            $markdown .= "\n";
        }

        $markdown .= "## Skeleton/config\n\n";
        $skeleton = $this->arrayValue($report, 'skeleton');

        if ($skeleton === []) {
            $markdown .= "No skeleton or config changes were recorded.\n\n";
        } else {
            foreach ($skeleton as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $markdown .= sprintf(
                    "- **%s** (%s): %s\n",
                    $this->scalar($change['transition'] ?? $change['major'] ?? 'unknown'),
                    $this->scalar($change['status'] ?? 'unknown'),
                    $this->scalar($change['message'] ?? 'Skeleton step recorded.'),
                );
                $files = $this->stringList($change['changedFiles'] ?? []);

                if ($files !== []) {
                    $markdown .= '  - Merged files: '.implode(', ', array_map([$this, 'code'], $files))."\n";
                }

                $keysAdded = $this->stringList($change['keysAdded'] ?? []);

                if ($keysAdded !== []) {
                    $markdown .= '  - Keys added: '.implode(', ', array_map([$this, 'code'], $keysAdded))."\n";
                }

                $preserved = $this->stringList($change['preservedValues'] ?? []);

                if ($preserved !== []) {
                    $markdown .= '  - Preserved values: '.implode(', ', array_map([$this, 'code'], $preserved))."\n";
                }

                $conflicts = $this->stringList($change['conflicts'] ?? []);

                if ($conflicts !== []) {
                    $markdown .= '  - Conflicts: '.implode(', ', array_map([$this, 'code'], $conflicts))."\n";
                }
            }

            $markdown .= "\n";
        }

        $markdown .= "## Advisories\n\n";
        $advisories = array_merge($medium, $low, $info);

        if ($advisories === []) {
            $markdown .= "No advisories were recorded.\n\n";
        } else {
            /** @var array<string, list<array<string, mixed>>> $grouped */
            $grouped = [];

            foreach ($advisories as $finding) {
                $ruleId = $this->scalar($finding['ruleId'] ?? 'unknown');
                $grouped[$ruleId][] = $finding;
            }

            ksort($grouped);

            foreach ($grouped as $ruleId => $ruleFindings) {
                $markdown .= sprintf("- **%s** (%d)\n", $ruleId, count($ruleFindings));

                foreach (array_slice($ruleFindings, 0, 20) as $finding) {
                    $markdown .= '  - '.$this->location($finding).' — '.$this->scalar($finding['message'] ?? '')."\n";
                }
            }

            $markdown .= "\n";
        }

        $markdown .= "## Verification\n\n";
        $verification = $this->arrayValue($report, 'verification');

        if ($verification === []) {
            $markdown .= "No verification results were recorded.\n\n";
        } else {
            foreach ($verification as $check => $value) {
                if (is_array($value) && array_key_exists('transition', $value)) {
                    $label = $this->scalar($value['transition']);
                    $status = $this->scalar($value['status'] ?? 'unknown');
                    $message = $this->scalar($value['message'] ?? '');
                    $markdown .= sprintf('- **%s:** %s', $label, $status);

                    if ($message !== '') {
                        $markdown .= ' — '.$message;
                    }

                    $markdown .= "\n";
                    $verificationData = $value['data'] ?? null;
                    $checks = is_array($verificationData) ? ($verificationData['checks'] ?? null) : null;

                    if (is_array($checks)) {
                        foreach ($checks as $name => $checkResult) {
                            $markdown .= sprintf("  - **%s:** %s\n", $this->code((string) $name), $this->value($checkResult));
                        }
                    }

                    continue;
                }

                if (is_array($value) && array_is_list($value)) {
                    $markdown .= sprintf("- **%s:**\n", $this->code((string) $check));

                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $status = $this->scalar($item['status'] ?? 'unknown');
                            $detail = is_string($item['file'] ?? null) ? ' '.$this->code($item['file']) : '';
                            $markdown .= sprintf("  - %s%s\n", $status, $detail);
                        } else {
                            $markdown .= '  - '.$this->value($item)."\n";
                        }
                    }

                    continue;
                }

                if (is_array($value) && array_key_exists('status', $value)) {
                    $status = $this->scalar($value['status']);
                    $detail = isset($value['reason']) ? ' — '.$this->scalar($value['reason']) : '';
                    $markdown .= sprintf("- **%s:** %s%s\n", $this->code((string) $check), $status, $detail);

                    continue;
                }

                $markdown .= sprintf("- **%s:** %s\n", $this->code((string) $check), $this->value($value));
            }

            $markdown .= "\n";
        }

        $markdown .= "## What the tool did not do\n\n";
        $notDone = $this->stringList($report['whatToolDidNotDo'] ?? []);

        if ($notDone === []) {
            $markdown .= "No limitations were recorded.\n";
        } else {
            foreach ($notDone as $item) {
                $markdown .= '- '.$item."\n";
            }
        }

        $written = @file_put_contents($targetPath, $markdown);

        if ($written !== strlen($markdown)) {
            throw new \RuntimeException(sprintf('Could not write Markdown report "%s".', $targetPath));
        }
    }

    /**
     * Backwards-compatible JSON renderer. New callers should persist the
     * complete canonical document through UpgradeReportGenerator.
     *
     * @param  list<Finding>  $findings
     * @param  array<string, mixed>  $project
     */
    public function writeJson(array $findings, array $project, string $targetPath): void
    {
        $report = [
            'schemaVersion' => 1,
            'tool' => PackageInfo::TOOL,
            'runId' => 'unknown',
            'startedAt' => gmdate(DATE_ATOM),
            'finishedAt' => gmdate(DATE_ATOM),
            'project' => $project,
            'steps' => [],
            'dependencies' => [],
            'codeChanges' => [],
            'skeleton' => [],
            'findings' => array_map(
                static fn (Finding $finding): array => $finding->toArray(),
                $findings,
            ),
            'verification' => [],
            'whatToolDidNotDo' => [],
        ];

        $contents = (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $written = @file_put_contents($targetPath, $contents);

        if ($written !== strlen($contents)) {
            throw new \RuntimeException(sprintf('Could not write JSON report "%s".', $targetPath));
        }
    }

    /** @param array<string, mixed> $report
     * @return array<mixed>
     */
    private function arrayValue(array $report, string $key): array
    {
        $value = $report[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /** @param array<mixed> $values
     * @return list<array<string, mixed>>
     */
    private function findingArrays(array $values): array
    {
        $findings = [];

        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            $finding = [];

            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $finding[$key] = $item;
                }
            }

            $findings[] = $finding;
        }

        return $findings;
    }

    /** @param list<array<string, mixed>> $findings
     * @return list<array<string, mixed>>
     */
    private function findingsBySeverity(array $findings, string $severity): array
    {
        return array_values(array_filter(
            $findings,
            static fn (array $finding): bool => ($finding['severity'] ?? null) === $severity,
        ));
    }

    /** @param array<string, mixed> $finding */
    private function findingLine(array $finding, bool $includeAction): string
    {
        $line = sprintf(
            '- **%s** — %s',
            $this->location($finding),
            $this->scalar($finding['message'] ?? 'Unknown finding.'),
        );

        if ($includeAction) {
            $line .= ' Action: '.$this->scalar($finding['action'] ?? 'Review manually.');
        }

        $guide = $finding['guideUrl'] ?? '';

        if (is_string($guide) && $guide !== '') {
            $line .= ' Guide: '.$guide;
        }

        return $line."\n";
    }

    /** @param array<string, mixed> $finding */
    private function location(array $finding): string
    {
        return sprintf(
            '%s:%s',
            $this->code($this->scalar($finding['file'] ?? 'unknown')),
            $this->scalar($finding['line'] ?? 0),
        );
    }

    /** @return list<string> */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $strings = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }

    private function scalar(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) ($value ?? 'unknown');
        }

        return 'unknown';
    }

    private function code(string $value): string
    {
        return '`'.str_replace('`', '\\`', $value).'`';
    }

    private function cell(mixed $value): string
    {
        return str_replace('|', '\\|', $this->scalar($value));
    }

    private function value(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'passed' : 'failed';
        }

        if (is_scalar($value) || $value === null) {
            return $this->scalar($value);
        }

        if (is_array($value)) {
            return $this->scalar(json_encode($value, JSON_UNESCAPED_SLASHES));
        }

        return 'unknown';
    }

    /** @param array<mixed> $rules */
    private function formatRuleCounts(array $rules): string
    {
        $formatted = [];

        foreach ($rules as $rule => $count) {
            if (is_string($rule)) {
                $formatted[] = $rule.': '.$this->scalar($count);
            } elseif (is_string($count)) {
                $formatted[] = $count;
            }
        }

        return implode(', ', $formatted);
    }
}
