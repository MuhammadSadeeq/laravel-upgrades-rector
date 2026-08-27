<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Journal;

use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use RuntimeException;

/**
 * Atomic journal for resumable upgrades.
 *
 * Plan-mode stores are intentionally memory-only: no directory creation,
 * temporary files, or state-file writes occur when $planMode is true.
 */
final class StateStore
{
    private const SCHEMA_VERSION = 1;

    public const STATUS_RUNNING = 'running';

    public const STATUS_FAILED = 'failed';

    public const STATUS_COMPLETED = 'completed';

    /** @var array<string, mixed>|null */
    private ?array $memoryState = null;

    public function __construct(
        private readonly string $workingDirectory,
        private readonly bool $planMode = false,
    ) {
        if ($workingDirectory === '') {
            throw new RuntimeException('An upgrade state working directory is required.');
        }
    }

    public function path(): string
    {
        return rtrim($this->workingDirectory, '/\\').'/.laravel-upgrade/state.json';
    }

    public function directory(): string
    {
        return dirname($this->path());
    }

    public function workingDirectory(): string
    {
        return $this->workingDirectory;
    }

    public function isPlanMode(): bool
    {
        return $this->planMode;
    }

    /**
     * Create or resume a journal for a plan.
     *
     * @return array<string, mixed>
     */
    public function start(UpgradePlan $plan, ?string $runId = null): array
    {
        $existing = $this->load();

        if (! $this->planMode && is_file($this->path()) && $existing === null) {
            throw new RuntimeException('The upgrade state file is corrupt or has an unsupported schema.');
        }

        if ($existing !== null) {
            $existingTarget = $existing['target'] ?? null;
            $existingStatus = $existing['status'] ?? null;

            if (! is_int($existingTarget) || ! is_string($existingStatus)) {
                throw new RuntimeException('The upgrade state file is corrupt or has an unsupported schema.');
            }

            if ($existingStatus !== self::STATUS_COMPLETED && $existingTarget !== $plan->targetMajor) {
                throw new StateConflictException(sprintf(
                    'An active Laravel %d upgrade already exists; refusing to start Laravel %d.',
                    $existingTarget,
                    $plan->targetMajor,
                ));
            }

            if ($existingStatus !== self::STATUS_COMPLETED && $existingTarget === $plan->targetMajor) {
                return $existing;
            }
        }

        $now = $this->now();
        $transitions = $plan->transitions();
        $state = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'runId' => $runId ?? $this->newRunId(),
            'target' => $plan->targetMajor,
            'currentMajor' => $plan->currentMajor,
            'currentTransition' => $transitions === []
                ? null
                : UpgradePlan::transitionLabel($plan->currentMajor, $transitions[0]),
            'completedSteps' => [],
            'changedFiles' => [],
            'findingsCount' => 0,
            'status' => $plan->isNoOp() ? self::STATUS_COMPLETED : self::STATUS_RUNNING,
            'startedAt' => $now,
            'updatedAt' => $now,
            'timestamps' => [
                'startedAt' => $now,
                'updatedAt' => $now,
            ],
        ];

        if (! $plan->isNoOp()) {
            $this->persist($state);
        } elseif ($this->planMode) {
            $this->memoryState = $state;
        }

        return $state;
    }

    /**
     * Load and validate a journal. Missing and malformed journals both return
     * null so callers can present a safe "no resume state" result.
     *
     * @return array<string, mixed>|null
     */
    public function load(): ?array
    {
        if ($this->planMode && $this->memoryState !== null) {
            return $this->memoryState;
        }

        if (! is_file($this->path())) {
            return null;
        }

        $json = file_get_contents($this->path());

        if (! is_string($json) || trim($json) === '') {
            return null;
        }

        try {
            $state = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($state)) {
            return null;
        }

        $stringKeyState = [];

        foreach ($state as $key => $value) {
            if (! is_string($key)) {
                return null;
            }

            $stringKeyState[$key] = $value;
        }

        return $this->isValidState($stringKeyState) ? $stringKeyState : null;
    }

    /**
     * Record a successful canonical step in the current transition.
     *
     * @param  list<string>  $changedFiles
     * @return array<string, mixed>
     */
    public function recordCompletedStep(
        UpgradePlan $plan,
        string $step,
        array $changedFiles = [],
        int $findingsCount = 0,
    ): array {
        return $this->recordStep($plan, $step, 'completed', '', $changedFiles, $findingsCount);
    }

    /**
     * Record a deliberately skipped step so resume state remains deterministic.
     *
     * @return array<string, mixed>
     */
    public function recordSkippedStep(UpgradePlan $plan, string $step, string $message = 'Skipped by plan'): array
    {
        return $this->recordStep($plan, $step, 'skipped', $message);
    }

    /**
     * @param  list<string>  $changedFiles
     * @return array<string, mixed>
     */
    private function recordStep(
        UpgradePlan $plan,
        string $step,
        string $stepStatus,
        string $stepMessage,
        array $changedFiles = [],
        int $findingsCount = 0,
    ): array {
        if ($findingsCount < 0) {
            throw new RuntimeException('A step finding count cannot be negative.');
        }

        foreach ($changedFiles as $changedFile) {
            if (! is_string($changedFile)) {
                throw new RuntimeException('A changed file path must be a string.');
            }
        }

        $state = $this->requireState($plan);
        $this->assertCanonicalStep($plan, $step);

        $transition = $this->currentTransition($state);
        $completedSteps = $this->completedSteps($state);
        $completedSteps[$transition] ??= [];
        $completedSteps[$transition][$step] = [
            'status' => $stepStatus,
            'message' => $stepMessage,
            'completedAt' => $this->now(),
            'changedFiles' => array_values($changedFiles),
            'findingsCount' => $findingsCount,
        ];
        $state['completedSteps'] = $completedSteps;
        $state['changedFiles'] = array_values(array_unique(array_merge(
            $this->changedFiles($state),
            $changedFiles,
        )));
        $state['findingsCount'] = $this->findingsCount($state) + $findingsCount;
        $state['status'] = self::STATUS_RUNNING;
        unset($state['failedStep'], $state['failureMessage']);
        $this->touch($state);

        [, $transitionTarget] = $this->parseTransition($transition);
        $selectedSteps = $plan->stepsForTransition($transitionTarget);
        $allSelectedStepsComplete = true;

        foreach ($selectedSteps as $selectedStep) {
            if (! array_key_exists($selectedStep, $completedSteps[$transition])) {
                $allSelectedStepsComplete = false;
                break;
            }
        }

        if ($allSelectedStepsComplete) {
            $state['currentMajor'] = $transitionTarget;
            $nextTransition = $this->nextTransition($plan, $transitionTarget);

            if ($nextTransition === null) {
                $state['currentTransition'] = null;
                $state['status'] = self::STATUS_COMPLETED;
            } else {
                $state['currentTransition'] = $nextTransition;
            }
        }

        $this->persist($state);

        if ($state['status'] === self::STATUS_COMPLETED) {
            $this->clearCompleted();
        }

        return $state;
    }

    /**
     * Record a failed step while leaving the journal available for continue.
     *
     * @return array<string, mixed>
     */
    public function recordFailedStep(UpgradePlan $plan, string $step, string $message = ''): array
    {
        $state = $this->requireState($plan);
        $this->assertCanonicalStep($plan, $step);
        $state['status'] = self::STATUS_FAILED;
        $state['failedStep'] = $step;
        $state['failureMessage'] = $message;
        $this->touch($state);
        $this->persist($state);

        return $state;
    }

    /**
     * Return the first selected step not recorded for the active transition.
     */
    public function firstIncompleteStep(UpgradePlan $plan): ?string
    {
        if ($plan->isNoOp()) {
            return null;
        }

        $state = $this->load();

        if ($state === null) {
            return $plan->steps()[0] ?? null;
        }

        if ($state['status'] === self::STATUS_COMPLETED) {
            return null;
        }

        $transition = $this->currentTransition($state);
        [, $transitionTarget] = $this->parseTransition($transition);
        $completedSteps = $this->completedSteps($state);
        $completed = $completedSteps[$transition] ?? [];

        foreach ($plan->stepsForTransition($transitionTarget) as $step) {
            if (! array_key_exists($step, $completed)) {
                return $step;
            }
        }

        // A malformed or stale journal should never make the runner skip all
        // work; the first canonical step is the safe fallback.
        return $plan->steps()[0] ?? null;
    }

    /**
     * Remove a journal only when its latest state is actually completed.
     */
    public function clearCompleted(): void
    {
        if ($this->planMode) {
            return;
        }

        $state = $this->load();

        if ($state === null) {
            return;
        }

        if ($state['status'] !== self::STATUS_COMPLETED) {
            throw new RuntimeException('An incomplete upgrade journal cannot be cleared.');
        }

        if (is_file($this->path()) && ! unlink($this->path())) {
            throw new RuntimeException('Unable to clear the completed upgrade journal.');
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function persist(array $state): void
    {
        if ($this->planMode) {
            $this->memoryState = $state;

            return;
        }

        if (! is_dir($this->directory()) && ! mkdir($this->directory(), 0777, true) && ! is_dir($this->directory())) {
            throw new RuntimeException('Unable to create the upgrade journal directory.');
        }

        try {
            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode the upgrade journal.', 0, $exception);
        }

        $temporaryPath = tempnam($this->directory(), 'state-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create an atomic upgrade journal temporary file.');
        }

        try {
            $bytes = file_put_contents($temporaryPath, $json, LOCK_EX);

            if ($bytes !== strlen($json)) {
                throw new RuntimeException('Unable to write the complete upgrade journal.');
            }

            if (! rename($temporaryPath, $this->path())) {
                throw new RuntimeException('Unable to atomically replace the upgrade journal.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function isValidState(array $state): bool
    {
        if (($state['schemaVersion'] ?? null) !== self::SCHEMA_VERSION
            || ! is_string($state['runId'] ?? null)
            || $state['runId'] === ''
            || ! is_int($state['target'] ?? null)
            || ! is_int($state['currentMajor'] ?? null)
            || ! (is_string($state['currentTransition'] ?? null) || $state['currentTransition'] === null)
            || ! is_array($state['completedSteps'] ?? null)
            || ! in_array($state['status'] ?? null, [self::STATUS_RUNNING, self::STATUS_FAILED, self::STATUS_COMPLETED], true)
            || ! is_string($state['startedAt'] ?? null)
            || ! is_string($state['updatedAt'] ?? null)
            || ! is_array($state['timestamps'] ?? null)
            || ! is_string($state['timestamps']['startedAt'] ?? null)
            || ! is_string($state['timestamps']['updatedAt'] ?? null)) {
            return false;
        }

        foreach ($state['completedSteps'] as $transition => $steps) {
            if (! is_string($transition)
                || preg_match('/^\d+->\d+$/', $transition) !== 1
                || ! is_array($steps)) {
                return false;
            }

            foreach ($steps as $step => $stepResult) {
                if (! is_string($step)
                    || ! UpgradePlan::isCanonicalStep($step)
                    || ! is_array($stepResult)
                    || ! in_array($stepResult['status'] ?? null, ['completed', 'skipped'], true)
                    || ! is_string($stepResult['message'] ?? null)
                    || ! is_string($stepResult['completedAt'] ?? null)
                    || ! is_array($stepResult['changedFiles'] ?? null)
                    || ! is_int($stepResult['findingsCount'] ?? null)
                    || $stepResult['findingsCount'] < 0) {
                    return false;
                }

                foreach ($stepResult['changedFiles'] as $changedFile) {
                    if (! is_string($changedFile)) {
                        return false;
                    }
                }
            }
        }

        if (! is_array($state['changedFiles'] ?? null)
            || ! is_int($state['findingsCount'] ?? null)
            || $state['findingsCount'] < 0) {
            return false;
        }

        foreach ($state['changedFiles'] as $changedFile) {
            if (! is_string($changedFile)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireState(UpgradePlan $plan): array
    {
        $state = $this->load();

        if ($state === null) {
            $state = $this->start($plan);
        }

        $target = $state['target'] ?? null;

        if (! is_int($target)) {
            throw new RuntimeException('The upgrade journal has no valid target major.');
        }

        if ($target !== $plan->targetMajor) {
            throw new StateConflictException(sprintf(
                'The journal targets Laravel %d, not Laravel %d.',
                $target,
                $plan->targetMajor,
            ));
        }

        return $state;
    }

    private function assertCanonicalStep(UpgradePlan $plan, string $step): void
    {
        if (! in_array($step, $plan->canonicalSteps(), true)) {
            throw new RuntimeException(sprintf('Unknown upgrade step "%s".', $step));
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function currentTransition(array $state): string
    {
        $transition = $state['currentTransition'] ?? null;

        if (! is_string($transition) || $transition === '') {
            throw new RuntimeException('The upgrade journal has no active transition.');
        }

        return $transition;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, array<string, array{status: string, message: string, completedAt: string, changedFiles: list<string>, findingsCount: int}>>
     */
    private function completedSteps(array $state): array
    {
        $raw = $state['completedSteps'] ?? null;

        if (! is_array($raw)) {
            throw new RuntimeException('The upgrade journal has invalid completed steps.');
        }

        $completedSteps = [];

        foreach ($raw as $transition => $steps) {
            if (! is_string($transition) || ! is_array($steps)) {
                throw new RuntimeException('The upgrade journal has invalid completed steps.');
            }

            $completedSteps[$transition] = [];

            foreach ($steps as $step => $result) {
                if (! is_string($step) || ! is_array($result)) {
                    throw new RuntimeException('The upgrade journal has invalid completed steps.');
                }

                $completedAt = $result['completedAt'] ?? null;
                $status = $result['status'] ?? null;
                $message = $result['message'] ?? null;
                $changedFiles = $result['changedFiles'] ?? null;
                $findingsCount = $result['findingsCount'] ?? null;

                if (! is_string($status)
                    || ! is_string($message)
                    || ! is_string($completedAt)
                    || ! is_array($changedFiles)
                    || ! is_int($findingsCount)) {
                    throw new RuntimeException('The upgrade journal has invalid completed step metadata.');
                }

                $files = [];

                foreach ($changedFiles as $file) {
                    if (! is_string($file)) {
                        throw new RuntimeException('The upgrade journal has invalid changed file metadata.');
                    }

                    $files[] = $file;
                }

                $completedSteps[$transition][$step] = [
                    'status' => $status,
                    'message' => $message,
                    'completedAt' => $completedAt,
                    'changedFiles' => $files,
                    'findingsCount' => $findingsCount,
                ];
            }
        }

        return $completedSteps;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    private function changedFiles(array $state): array
    {
        $changedFiles = $state['changedFiles'] ?? null;

        if (! is_array($changedFiles)) {
            throw new RuntimeException('The upgrade journal has invalid changed files.');
        }

        $files = [];

        foreach ($changedFiles as $changedFile) {
            if (! is_string($changedFile)) {
                throw new RuntimeException('The upgrade journal has invalid changed files.');
            }

            $files[] = $changedFile;
        }

        return $files;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function findingsCount(array $state): int
    {
        $findingsCount = $state['findingsCount'] ?? null;

        if (! is_int($findingsCount) || $findingsCount < 0) {
            throw new RuntimeException('The upgrade journal has an invalid findings count.');
        }

        return $findingsCount;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseTransition(string $transition): array
    {
        if (preg_match('/^(\d+)->(\d+)$/', $transition, $matches) !== 1) {
            throw new RuntimeException('The upgrade journal contains an invalid transition.');
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    private function nextTransition(UpgradePlan $plan, int $currentMajor): ?string
    {
        foreach ($plan->transitions() as $target) {
            if ($target > $currentMajor) {
                return UpgradePlan::transitionLabel($currentMajor, $target);
            }
        }

        return null;
    }

    private function now(): string
    {
        return gmdate(DATE_ATOM);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function touch(array &$state): void
    {
        $now = $this->now();
        $state['updatedAt'] = $now;
        $state['timestamps'] = [
            'startedAt' => $state['startedAt'] ?? $now,
            'updatedAt' => $now,
        ];
    }

    private function newRunId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
