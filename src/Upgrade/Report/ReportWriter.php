<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report;

/**
 * Writes UPGRADE-REPORT.md and report.json from collected findings
 * (plan P3-01, Appendix D layout).
 */
final class ReportWriter
{
    /**
     * @param  list<Finding>  $findings
     * @param  array{from: string, to: string, php: string, commits: int, duration: string}  $project
     */
    public function writeMarkdown(array $findings, array $project, string $targetPath): void
    {
        $high = array_filter($findings, static fn ($f) => $f->severity === Finding::SEVERITY_HIGH);
        $medium = array_filter($findings, static fn ($f) => $f->severity === Finding::SEVERITY_MEDIUM);
        $low = array_filter($findings, static fn ($f) => $f->severity === Finding::SEVERITY_LOW);

        $md = "# Laravel Upgrade Report\n\n";
        $md .= sprintf("**From:** %s → **To:** %s  \n", $project['from'] ?? '?', $project['to'] ?? '?');
        $md .= sprintf("**PHP:** %s  \n", $project['php'] ?? '?');
        $md .= sprintf("**Commits:** %d  **Duration:** %s\n\n", $project['commits'] ?? 0, $project['duration'] ?? '?');

        $md .= sprintf(
            "| Severity | Count |\n|---|---|\n| High | %d |\n| Medium | %d |\n| Low | %d |\n\n",
            count($high),
            count($medium),
            count($low)
        );

        if ($high !== []) {
            $md .= "## Manual Actions (High)\n\n";

            foreach ($high as $finding) {
                $md .= sprintf("- **%s:%d** — %s\n  - Action: %s\n", $finding->file, $finding->line, $finding->message, $finding->action);

                if ($finding->guideUrl !== '') {
                    $md .= sprintf("  - Guide: %s\n", $finding->guideUrl);
                }
            }

            $md .= "\n";
        }

        if ($medium !== []) {
            $md .= "## Advisories (Medium)\n\n";

            foreach ($medium as $finding) {
                $md .= sprintf("- **%s:%d** — %s\n", $finding->file, $finding->line, $finding->message);
            }

            $md .= "\n";
        }

        if ($low !== []) {
            $md .= sprintf("## Low-Priority Items (%d)\n\n", count($low));

            foreach ($low as $finding) {
                $md .= sprintf("- %s:%d — %s\n", $finding->file, $finding->line, $finding->message);
            }
        }

        file_put_contents($targetPath, $md);
    }

    /**
     * @param  list<Finding>  $findings
     * @param  array<string, mixed>  $project
     */
    public function writeJson(array $findings, array $project, string $targetPath): void
    {
        $report = [
            'tool' => 'laravel-upgrades-rector',
            'generatedAt' => date('c'),
            'project' => $project,
            'findings' => array_map(static fn (Finding $f): array => $f->toArray(), $findings),
        ];

        file_put_contents($targetPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
}
