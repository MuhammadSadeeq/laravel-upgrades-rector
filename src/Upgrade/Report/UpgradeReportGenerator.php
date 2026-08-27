<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report;

use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use RuntimeException;

/**
 * Materialises the final report immediately before the commit checkpoint.
 * Both outputs are written through temporary files and atomically renamed;
 * plan mode returns a preview without creating the upgrade directory.
 */
final class UpgradeReportGenerator
{
    public function __construct(private readonly ReportWriter $writer = new ReportWriter) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(UpgradeContext $context): array
    {
        if ($context->isPlanMode()) {
            return ['status' => 'skipped', 'reason' => 'plan-mode'];
        }

        $project = rtrim($context->workingDirectory, '/\\');
        $directory = $project.'/.laravel-upgrade';

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the upgrade report directory.');
        }

        $findings = $this->readFindings($directory.'/findings.jsonl');
        // The report describes the complete requested upgrade. During a
        // multi-major run the context's active transition is intentionally
        // narrower than this overall plan.
        $metadata = [
            'from' => (string) $context->currentMajor(),
            'to' => (string) $context->targetMajor(),
            'php' => PHP_VERSION,
            'commits' => 0,
            'duration' => '',
        ];
        $markdown = $project.'/UPGRADE-REPORT.md';
        $json = $directory.'/report.json';

        $this->atomicWrite($markdown, function (string $temporaryPath) use ($findings, $metadata): void {
            $this->writer->writeMarkdown($findings, $metadata, $temporaryPath);
        });
        $this->atomicWrite($json, function (string $temporaryPath) use ($findings, $metadata): void {
            $this->writer->writeJson($findings, $metadata, $temporaryPath);
        });

        return [
            'status' => 'generated',
            'markdown' => $markdown,
            'json' => $json,
            'findings' => count($findings),
        ];
    }

    /** @return list<Finding> */
    private function readFindings(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new RuntimeException('Could not read accumulated upgrade findings.');
        }

        $findings = [];

        foreach ($lines as $lineNumber => $line) {
            try {
                /** @var mixed $decoded */
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException(sprintf('Invalid finding JSON on line %d.', $lineNumber + 1), 0, $exception);
            }

            if (! is_array($decoded)) {
                throw new RuntimeException(sprintf('Finding JSON on line %d must be an object.', $lineNumber + 1));
            }

            /** @var array<string, mixed> $decoded */
            $findings[] = Finding::fromArray($decoded);
        }

        return $findings;
    }

    /** @param callable(string): void $write */
    private function atomicWrite(string $target, callable $write): void
    {
        $temporary = tempnam(dirname($target), '.upgrade-report-');

        if ($temporary === false) {
            throw new RuntimeException(sprintf('Could not create a temporary report for "%s".', $target));
        }

        try {
            $write($temporary);

            if (! rename($temporary, $target)) {
                throw new RuntimeException(sprintf('Could not atomically write "%s".', $target));
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
