<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report;

use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\StepExecutionResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use RuntimeException;

/**
 * Maintains the canonical, versioned report for one upgrade run.
 *
 * Apply-mode updates are written after every runner step. Plan mode is a
 * strict no-write path; its only project artifact is written by PlanCommand.
 */
final class UpgradeReportGenerator
{
    public const SCHEMA_VERSION = 1;

    public const TOOL = 'laravel-upgrades-rector/2.0.0';

    public function __construct(private readonly ReportWriter $writer = new ReportWriter) {}

    /**
     * Add or replace one transition/step entry and regenerate both reports.
     *
     * @return array<string, mixed>
     */
    public function recordStep(UpgradeContext $context, StepExecutionResult $execution): array
    {
        if ($context->isPlanMode()) {
            return ['status' => 'skipped', 'reason' => 'plan-mode'];
        }

        $directory = $this->ensureDirectory($context->workingDirectory);
        $path = $directory.'/report.json';
        $report = $this->loadOrCreate($context, $path);
        $entry = $this->stepEntry($execution);
        $entries = $this->arrayValue($report, 'steps');
        $identity = $execution->transition."\0".$execution->step;
        $replaced = false;

        foreach ($entries as $index => $existing) {
            if (! is_array($existing)) {
                continue;
            }

            $existingIdentity = $this->stringValue($existing['transition'] ?? null)."\0".$this->stringValue($existing['name'] ?? ($existing['step'] ?? null));

            if ($existingIdentity === $identity) {
                $entries[$index] = $entry;
                $replaced = true;
                break;
            }
        }

        if (! $replaced) {
            $entries[] = $entry;
        }

        $report['steps'] = $this->orderSteps($entries, $context);
        $report['findings'] = $this->mergeFindings(
            $this->arrayValue($report, 'findings'),
            $this->readJsonl($directory.'/findings.jsonl'),
            $this->extractFindings($entry['data'] ?? []),
        );
        $report = $this->deriveSections($report);

        if ($execution->result->isFailed()) {
            // A report prepared for a final git checkpoint is not complete if
            // that checkpoint fails. Keep the partial report useful while
            // making its incomplete state explicit.
            $report['finishedAt'] = null;
        }

        $report['updatedAt'] = $this->timestamp();
        $this->writeReport($context->workingDirectory, $report);

        return [
            'status' => 'updated',
            'report' => $path,
            'step' => $execution->step,
            'transition' => $execution->transition,
        ];
    }

    /**
     * Finish a report, preserving all accumulated steps and findings.
     *
     * @return array<string, mixed>
     */
    public function generate(UpgradeContext $context): array
    {
        if ($context->isPlanMode()) {
            return ['status' => 'skipped', 'reason' => 'plan-mode'];
        }

        $directory = $this->ensureDirectory($context->workingDirectory);
        $report = $this->loadOrCreate($context, $directory.'/report.json');
        $report['findings'] = $this->mergeFindings(
            $this->arrayValue($report, 'findings'),
            $this->readJsonl($directory.'/findings.jsonl'),
        );
        $report = $this->deriveSections($report);
        $report['finishedAt'] = $context->toMajor() === $context->targetMajor()
            ? $this->timestamp()
            : null;
        $report['updatedAt'] = $this->timestamp();
        $this->writeReport($context->workingDirectory, $report);

        return [
            'status' => 'generated',
            'markdown' => rtrim($context->workingDirectory, '/\\').'/UPGRADE-REPORT.md',
            'json' => $directory.'/report.json',
            'findings' => count($this->arrayValue($report, 'findings')),
        ];
    }

    /**
     * Read the canonical report and regenerate only the root Markdown file.
     *
     * @return array<string, mixed>
     */
    public function regenerate(string $workingDirectory): array
    {
        $workingDirectory = realpath($workingDirectory) ?: $workingDirectory;
        $path = rtrim($workingDirectory, '/\\').'/.laravel-upgrade/report.json';
        $report = $this->loadCanonical($path);
        $markdown = rtrim($workingDirectory, '/\\').'/UPGRADE-REPORT.md';
        $this->atomicWrite($markdown, function (string $temporaryPath) use ($report): void {
            $this->writer->writeMarkdownReport($report, $temporaryPath);
        });

        return [
            'status' => 'regenerated',
            'markdown' => $markdown,
            'json' => $path,
            'findings' => count($this->arrayValue($report, 'findings')),
        ];
    }

    public function canonicalPath(string $workingDirectory): string
    {
        return rtrim($workingDirectory, '/\\').'/.laravel-upgrade/report.json';
    }

    /**
     * Return the existing report run id, validating its canonical schema first.
     * A standalone engine command uses this id to append to the same report as
     a prior full run instead of replacing its accumulated history.
     */
    public function runIdFor(string $workingDirectory, string $fallback): string
    {
        $path = $this->canonicalPath($workingDirectory);

        if (! is_file($path)) {
            return $fallback;
        }

        $report = $this->loadCanonical($path);
        $runId = $report['runId'] ?? null;

        if (! is_string($runId) || $runId === '') {
            throw new RuntimeException(sprintf('Canonical report file "%s" has no valid run id.', $path));
        }

        return $runId;
    }

    /** @return array<string, mixed> */
    private function newReport(UpgradeContext $context): array
    {
        $now = $this->timestamp();

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'tool' => self::TOOL,
            'runId' => $context->runId,
            'startedAt' => $now,
            'finishedAt' => null,
            'updatedAt' => $now,
            'project' => [
                'name' => $this->projectName($context->workingDirectory),
                // These intentionally use the overall plan, not its active
                // one-major transition.
                'from' => (string) $context->currentMajor(),
                'to' => (string) $context->targetMajor(),
                'php' => PHP_VERSION,
                'composer' => 'unknown',
                'git' => ['branch' => 'unknown', 'base' => 'unknown'],
                'commits' => null,
                'duration' => 'unknown',
            ],
            'steps' => [],
            'dependencies' => [],
            'codeChanges' => [],
            'skeleton' => [],
            'findings' => [],
            'verification' => [],
            'verificationHistory' => [],
            'whatToolDidNotDo' => [
                'The .env file was never modified.',
                'package.json and frontend dependencies were not upgraded by the Laravel skeleton pass.',
                'Structure modernization was not performed while structure=keep.',
                'Unknown packages and application-specific behavior require manual review.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function loadOrCreate(UpgradeContext $context, string $path): array
    {
        if (! is_file($path)) {
            return $this->newReport($context);
        }

        $report = $this->loadCanonical($path);

        if (($report['runId'] ?? null) !== $context->runId) {
            return $this->newReport($context);
        }

        return $this->reconcileProjectBounds($report, $context);
    }

    /**
     * Keep the report's overall range when standalone commands are used for
     * successive transitions. Non-numeric values are deliberately preserved.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function reconcileProjectBounds(array $report, UpgradeContext $context): array
    {
        $project = $report['project'] ?? null;

        if (! is_array($project)) {
            return $report;
        }

        $contextFrom = $context->currentMajor();
        $contextTo = $context->targetMajor();
        $existingFrom = $this->numericMajor($project['from'] ?? null);
        $existingTo = $this->numericMajor($project['to'] ?? null);

        if ($existingFrom !== null) {
            $project['from'] = (string) min($existingFrom, $contextFrom);
        }

        if ($existingTo !== null) {
            $project['to'] = (string) max($existingTo, $contextTo);
        }

        $report['project'] = $project;

        return $report;
    }

    private function numericMajor(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function loadCanonical(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Canonical report file "%s" does not exist.', $path));
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw new RuntimeException(sprintf('Canonical report file "%s" is empty.', $path));
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('Canonical report file "%s" is invalid JSON.', $path), 0, $exception);
        }

        if (! is_array($decoded)
            || ($decoded['schemaVersion'] ?? null) !== self::SCHEMA_VERSION
            || ! is_string($decoded['tool'] ?? null)
            || ! is_string($decoded['runId'] ?? null)
            || ! is_array($decoded['project'] ?? null)
            || ! is_array($decoded['steps'] ?? null)
            || ! is_array($decoded['findings'] ?? null)) {
            throw new RuntimeException(sprintf('Canonical report file "%s" has an unsupported schema.', $path));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function ensureDirectory(string $workingDirectory): string
    {
        $directory = rtrim($workingDirectory, '/\\').'/.laravel-upgrade';

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create report directory "%s".', $directory));
        }

        return $directory;
    }

    /** @param array<mixed> $entries
     * @return list<array<string, mixed>>
     */
    private function orderSteps(array $entries, UpgradeContext $context): array
    {
        $stepOrder = array_flip($context->plan->canonicalSteps());

        usort($entries, static function (mixed $left, mixed $right) use ($stepOrder): int {
            if (! is_array($left) || ! is_array($right)) {
                return 0;
            }

            $leftTransition = is_string($left['transition'] ?? null) ? $left['transition'] : '';
            $rightTransition = is_string($right['transition'] ?? null) ? $right['transition'] : '';
            preg_match('/^(\d+)->\d+$/', $leftTransition, $leftMatch);
            preg_match('/^(\d+)->\d+$/', $rightTransition, $rightMatch);
            $leftMajor = isset($leftMatch[1]) ? (int) $leftMatch[1] : PHP_INT_MAX;
            $rightMajor = isset($rightMatch[1]) ? (int) $rightMatch[1] : PHP_INT_MAX;
            $leftStep = is_string($left['name'] ?? null) ? $left['name'] : (is_string($left['step'] ?? null) ? $left['step'] : '');
            $rightStep = is_string($right['name'] ?? null) ? $right['name'] : (is_string($right['step'] ?? null) ? $right['step'] : '');

            return [$leftMajor, $stepOrder[$leftStep] ?? PHP_INT_MAX] <=> [$rightMajor, $stepOrder[$rightStep] ?? PHP_INT_MAX];
        });

        $ordered = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $stringKeyEntry = [];

            foreach ($entry as $key => $value) {
                if (is_string($key)) {
                    $stringKeyEntry[$key] = $value;
                }
            }

            $ordered[] = $stringKeyEntry;
        }

        return $ordered;
    }

    /** @return array<string, mixed> */
    private function stepEntry(StepExecutionResult $execution): array
    {
        $result = $execution->result;
        $normalizedData = $this->normalize($result->data);
        $data = is_array($normalizedData) ? $this->stringKeyArray($normalizedData) : [];

        return [
            'transition' => $execution->transition,
            'major' => $execution->toMajor,
            'fromMajor' => $execution->fromMajor,
            'toMajor' => $execution->toMajor,
            'name' => $execution->step,
            'status' => $result->isFailed() ? 'failed' : ($result->isSkipped() ? 'skipped' : 'ok'),
            'durationMs' => 0,
            'changedFiles' => array_values($result->changedFiles),
            'commands' => $this->commandsFromData($data),
            'commit' => $this->commitFromData($data),
            'message' => $result->message,
            'findingsCount' => $result->findingsCount,
            'exitCode' => $result->exitCode,
            'recordedAt' => $this->timestamp(),
            'data' => $data,
        ];
    }

    /** @param array<string, mixed> $entry */
    private function commitFromEntry(array $entry): ?string
    {
        $commit = $entry['commit'] ?? null;

        return is_string($commit) && $commit !== '' ? $commit : null;
    }

    /** @param array<string, mixed> $data */
    private function commitFromData(array $data): ?string
    {
        $candidates = [$data['commit'] ?? null];

        if (is_array($data['git'] ?? null)) {
            $candidates[] = $data['git']['commit'] ?? null;
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && preg_match('/^[0-9a-f]{7,64}$/i', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $decision
     * @return array<string, mixed>
     */
    private function dependencyEntry(array $decision): array
    {
        $entry = $this->stringKeyArray($decision);

        if (! array_key_exists('from', $entry)) {
            $entry['from'] = $entry['current'] ?? null;
        }

        if (! array_key_exists('to', $entry)) {
            $entry['to'] = $entry['proposed'] ?? null;
        }

        $entry['installed'] = $entry['installed'] ?? null;
        unset($entry['current'], $entry['proposed']);

        return $entry;
    }

    private function verificationKey(string $id): string
    {
        if (str_starts_with($id, 'lint:')) {
            return 'lint';
        }

        return match ($id) {
            'composer-validate' => 'composerValidate',
            'class-load' => 'classLoad',
            'config-cache' => 'configCache',
            'config-clear' => 'configClear',
            'about' => 'boot',
            'routes' => 'routes',
            'tests', 'tests-summary' => 'tests',
            default => str_replace('-', '', $id),
        };
    }

    /** @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function deriveSections(array $report): array
    {
        $dependencies = [];
        $codeChanges = [];
        $skeleton = [];
        $verification = [];
        $verificationHistory = [];
        $commitCount = 0;

        foreach ($this->arrayValue($report, 'steps') as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $entry = $this->stringKeyArray($entry);

            $data = $entry['data'] ?? [];
            $data = is_array($data) ? $data : [];
            $name = $this->stringValue($entry['name'] ?? ($entry['step'] ?? null));

            if ($name === 'dependencies' && is_array($data['decisions'] ?? null)) {
                foreach ($data['decisions'] as $decision) {
                    if (is_array($decision)) {
                        $dependencies[] = $this->dependencyEntry($this->stringKeyArray($decision));
                    }
                }
            }

            if ($name === 'code') {
                $codeChanges[] = $entry + [
                    'appliedRules' => $data['appliedRules'] ?? [],
                    'appliedRuleCounts' => $data['appliedRuleCounts'] ?? [],
                ];
            }

            if ($name === 'skeleton') {
                $sync = is_array($data['sync'] ?? null) ? $data['sync'] : [];
                $skeleton[] = $entry + [
                    'mergedFiles' => $sync['changed'] ?? ($entry['changedFiles'] ?? []),
                    'keysAdded' => $sync['keysAdded'] ?? ($data['keysAdded'] ?? []),
                    'preservedValues' => $sync['preservedValues'] ?? ($data['preservedValues'] ?? []),
                    'conflicts' => $sync['conflicts'] ?? [],
                ];
            }

            if ($name === 'verify') {
                $historyEntry = [
                    'transition' => $entry['transition'] ?? 'unknown',
                    'status' => $entry['status'] ?? 'unknown',
                    'message' => $entry['message'] ?? '',
                    'data' => $data,
                ];
                $verificationHistory[] = $historyEntry;

                $checks = is_array($data['checks'] ?? null) ? $data['checks'] : [];

                foreach ($checks as $checkName => $check) {
                    if (is_array($check) && is_string($check['id'] ?? null)) {
                        $id = $this->verificationKey($check['id']);
                    } elseif (is_string($checkName)) {
                        $id = $this->verificationKey($checkName);
                        $check = [
                            'id' => $checkName,
                            'status' => $check === true ? 'success' : ($check === false ? 'failed' : 'recorded'),
                            'result' => $check,
                        ];
                    } else {
                        continue;
                    }

                    if ($id === 'lint') {
                        $existing = $verification[$id] ?? [];
                        $verification[$id] = array_is_list($existing)
                            ? array_merge($existing, [$check])
                            : [$check];
                    } else {
                        $verification[$id] = $check;
                    }
                }
            }

            $preparedFinalCommit = $name === 'commit'
                && ($data['reportState'] ?? null) === 'prepared'
                && ($entry['status'] ?? null) === 'ok';

            if ($this->commitFromEntry($entry) !== null || $preparedFinalCommit) {
                $commitCount++;
            }
        }

        $report['dependencies'] = $this->uniqueArrays($dependencies, ['package', 'section', 'from', 'to', 'reason']);
        $report['codeChanges'] = $codeChanges;
        $report['skeleton'] = $skeleton;
        $report['verification'] = $verification;
        $report['verificationHistory'] = $verificationHistory;

        if (is_array($report['project'] ?? null)) {
            $report['project']['commits'] = $commitCount > 0 ? $commitCount : null;
        }

        return $report;
    }

    /** @param array<mixed> $existing
     * @param  list<array<string, mixed>>  $jsonl
     * @param  list<array<string, mixed>>  $stepFindings
     * @return list<array<string, mixed>>
     */
    private function mergeFindings(array $existing, array $jsonl, array $stepFindings = []): array
    {
        $all = [];

        foreach (array_merge($existing, $jsonl, $stepFindings) as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            $normalized = $this->normalizeFinding($finding);

            if ($normalized !== null) {
                $all[] = $normalized;
            }
        }

        $unique = [];
        $seen = [];

        foreach ($all as $finding) {
            $key = implode("\0", [
                $this->stringValue($finding['ruleId'] ?? null),
                $this->stringValue($finding['major'] ?? ($finding['laravelVersion'] ?? null)),
                $this->stringValue($finding['file'] ?? null),
                $this->stringValue($finding['line'] ?? null),
                $this->stringValue($finding['message'] ?? null),
            ]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $finding;
        }

        return $this->uniqueFindingIds($unique);
    }

    /** @return list<array<string, mixed>> */
    private function readJsonl(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new RuntimeException(sprintf('Could not read findings file "%s".', $path));
        }

        $findings = [];

        foreach ($lines as $lineNumber => $line) {
            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException(sprintf('Invalid finding JSON on line %d.', $lineNumber + 1), 0, $exception);
            }

            if (is_array($decoded)) {
                $findings[] = $this->stringKeyArray($decoded);
            }
        }

        return $findings;
    }

    /** @return list<array<string, mixed>> */
    private function extractFindings(mixed $value): array
    {
        if ($value instanceof Finding) {
            $finding = $this->normalizeFinding($value->toArray());

            return $finding === null ? [] : [$finding];
        }

        if (! is_array($value)) {
            return [];
        }

        if (is_string($value['ruleId'] ?? null) && array_key_exists('message', $value)) {
            $finding = $this->normalizeFinding($this->stringKeyArray($value));

            return $finding === null ? [] : [$finding];
        }

        $findings = [];

        foreach ($value as $child) {
            foreach ($this->extractFindings($child) as $finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /** @param array<mixed> $finding
     * @return array<string, mixed>|null
     */
    private function normalizeFinding(array $finding): ?array
    {
        if (! is_string($finding['ruleId'] ?? null) || ! array_key_exists('message', $finding)) {
            return null;
        }

        $version = $finding['laravelVersion'] ?? $finding['major'] ?? 0;
        $version = is_int($version) ? $version : 0;
        $normalized = Finding::fromArray($this->stringKeyArray($finding))->toArray();
        $normalized['major'] = $version;

        return $normalized;
    }

    /** @param list<array<string, mixed>> $findings
     * @return list<array<string, mixed>>
     */
    private function uniqueFindingIds(array $findings): array
    {
        $used = [];
        $next = 1;

        foreach ($findings as $finding) {
            $id = $finding['id'] ?? '';

            if (is_string($id) && preg_match('/^f-(\d+)$/', $id, $match) === 1) {
                $next = max($next, (int) $match[1] + 1);
            }
        }

        foreach ($findings as $index => $finding) {
            $id = $finding['id'] ?? '';

            if (! is_string($id) || $id === '' || isset($used[$id])) {
                do {
                    $id = sprintf('f-%04d', $next++);
                } while (isset($used[$id]));

                $findings[$index]['id'] = $id;
            }

            $used[$id] = true;
        }

        return $findings;
    }

    /** @param list<array<string, mixed>> $values
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function uniqueArrays(array $values, array $keys): array
    {
        $unique = [];
        $seen = [];

        foreach ($values as $value) {
            $keyParts = [];

            foreach ($keys as $key) {
                $keyParts[] = $value[$key] ?? null;
            }

            $key = json_encode($keyParts, JSON_UNESCAPED_SLASHES);

            if (is_string($key) && isset($seen[$key])) {
                continue;
            }

            if (is_string($key)) {
                $seen[$key] = true;
            }

            $unique[] = $value;
        }

        return $unique;
    }

    /** @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function commandsFromData(array $data): array
    {
        $commands = [];
        $this->findCommands($data, $commands);
        $unique = [];
        $seen = [];

        foreach ($commands as $command) {
            $key = json_encode($command, JSON_UNESCAPED_SLASHES);

            if (! is_string($key) || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $command;
        }

        return $unique;
    }

    /** @param array<string, mixed> $value
     * @param  list<array<string, mixed>>  $commands
     */
    private function findCommands(array $value, array &$commands): void
    {
        $command = $value['command'] ?? null;

        if (is_array($command) && array_is_list($command) && array_filter($command, 'is_string') === $command) {
            $commands[] = [
                'cmd' => implode(' ', $command),
                'argv' => $command,
                'exit' => is_int($value['exitCode'] ?? null) ? $value['exitCode'] : null,
            ];
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $this->findCommands($this->stringKeyArray($child), $commands);
            }
        }
    }

    private function projectName(string $workingDirectory): string
    {
        $path = rtrim($workingDirectory, '/\\').'/composer.json';

        if (! is_file($path)) {
            return 'unknown';
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            return 'unknown';
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'unknown';
        }

        return is_array($decoded) && is_string($decoded['name'] ?? null) ? $decoded['name'] : 'unknown';
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof Finding) {
            return $value->toArray();
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $child) {
                $normalized[$key] = $this->normalize($child);
            }

            return $normalized;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return ['type' => get_debug_type($value)];
    }

    /** @param array<string, mixed> $report */
    private function writeReport(string $workingDirectory, array $report): void
    {
        $directory = $this->ensureDirectory($workingDirectory);
        $jsonPath = $directory.'/report.json';

        try {
            $contents = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not encode the canonical upgrade report.', 0, $exception);
        }

        $this->atomicWriteContents($jsonPath, $contents);
        $markdownPath = rtrim($workingDirectory, '/\\').'/UPGRADE-REPORT.md';
        $this->atomicWrite($markdownPath, function (string $temporaryPath) use ($report): void {
            $this->writer->writeMarkdownReport($report, $temporaryPath);
        });
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

    private function atomicWriteContents(string $target, string $contents): void
    {
        $temporary = tempnam(dirname($target), '.upgrade-report-');

        if ($temporary === false) {
            throw new RuntimeException(sprintf('Could not create a temporary report for "%s".', $target));
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || ! rename($temporary, $target)) {
                throw new RuntimeException(sprintf('Could not atomically write "%s".', $target));
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
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

    /** @param array<mixed> $value
     * @return array<string, mixed>
     */
    private function stringKeyArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) || is_bool($value)
            ? (string) $value
            : '';
    }

    private function timestamp(): string
    {
        return gmdate(DATE_ATOM);
    }
}
