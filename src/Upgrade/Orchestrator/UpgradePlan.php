<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator;

use InvalidArgumentException;
use LogicException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\SupportPolicy;

/**
 * The validated, side-effect-free description of an upgrade run.
 *
 * A plan deliberately contains no filesystem or process concerns. It can
 * therefore be used by both the preview command and the eventual runner.
 */
final class UpgradePlan
{
    public const MIN_SUPPORTED_TARGET = 11;

    public const MAX_SUPPORTED_TARGET = 13;

    /** @var list<string> */
    private const CANONICAL_STEPS = [
        'preflight',
        'dependencies',
        'install',
        'skeleton',
        'code',
        'advisories',
        'post',
        'verify',
        'commit',
    ];

    /**
     * @param  string|list<string>  $skipSteps
     */
    public function __construct(
        public readonly int $currentMajor,
        public readonly int $targetMajor,
        public readonly bool $planMode = false,
        ?string $fromStep = null,
        string|array $skipSteps = [],
        ?SupportPolicy $supportPolicy = null,
    ) {
        $usingDefaultPolicy = $supportPolicy === null;
        $supportPolicy ??= SupportPolicy::default();
        $supportedTargets = $supportPolicy->targetMajors();

        // Keep the historical constants source-compatible for callers that
        // read them, while ensuring they cannot silently drift from the
        // packaged policy document.
        if ($usingDefaultPolicy
            && ($supportPolicy->minTargetMajor() !== self::MIN_SUPPORTED_TARGET
                || $supportPolicy->maxTargetMajor() !== self::MAX_SUPPORTED_TARGET)
        ) {
            throw new LogicException('UpgradePlan compatibility constants do not match the support policy.');
        }

        if (! $supportPolicy->isSupportedTarget($targetMajor)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported Laravel target major %d; supported targets are %s.',
                $targetMajor,
                implode(', ', $supportedTargets),
            ));
        }

        if (! $supportPolicy->supportsMajor($currentMajor)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported current Laravel major %d; supported majors are %s.',
                $currentMajor,
                implode(', ', $supportPolicy->supportedMajors()),
            ));
        }

        if ($targetMajor < $currentMajor) {
            throw new InvalidArgumentException(sprintf(
                'Laravel target major %d is older than the detected current major %d.',
                $targetMajor,
                $currentMajor,
            ));
        }

        $fromStep = $fromStep === null ? null : trim($fromStep);

        if ($fromStep === '' || ($fromStep !== null && ! in_array($fromStep, self::CANONICAL_STEPS, true))) {
            throw new InvalidArgumentException(sprintf('Unknown upgrade step "%s".', $fromStep));
        }

        $normalizedSkipSteps = $this->normalizeStepList($skipSteps);

        if (in_array('verify', $normalizedSkipSteps, true)) {
            throw new InvalidArgumentException('The verify step cannot be skipped silently.');
        }

        $this->fromStep = $fromStep;
        $this->skipSteps = $normalizedSkipSteps;
        $this->supportPolicy = $supportPolicy;
    }

    public readonly ?string $fromStep;

    /** @var list<string> */
    public readonly array $skipSteps;

    private readonly SupportPolicy $supportPolicy;

    public function supportPolicy(): SupportPolicy
    {
        return $this->supportPolicy;
    }

    /**
     * Return each intermediate target exactly once, never as a direct jump
     * over an implemented adjacent path.
     *
     * @return list<int>
     */
    public function transitions(): array
    {
        $transitions = [];

        for ($major = $this->currentMajor + 1; $major <= $this->targetMajor; $major++) {
            $transitions[] = $major;
        }

        return $transitions;
    }

    /**
     * @return list<string>
     */
    public function canonicalSteps(): array
    {
        return self::canonicalStepNames();
    }

    /**
     * The single authoritative ordered step list used by plans and journals.
     *
     * @return list<string>
     */
    public static function canonicalStepNames(): array
    {
        return self::CANONICAL_STEPS;
    }

    public static function isCanonicalStep(string $step): bool
    {
        return in_array($step, self::CANONICAL_STEPS, true);
    }

    /**
     * Steps after applying --from-step and --skip-step.
     *
     * @return list<string>
     */
    public function steps(): array
    {
        $transitions = $this->transitions();

        if ($transitions !== []) {
            return $this->stepsForTransition($transitions[0]);
        }

        return $this->stepsFromCanonical(false);
    }

    /**
     * Return the selected steps for one transition. --from-step applies only
     * to the first transition; skips apply to every transition.
     *
     * @return list<string>
     */
    public function stepsForTransition(int $targetMajor): array
    {
        $transitions = $this->transitions();

        if (! in_array($targetMajor, $transitions, true)) {
            throw new InvalidArgumentException(sprintf(
                'Laravel transition target %d is not part of this upgrade plan.',
                $targetMajor,
            ));
        }

        return $this->stepsFromCanonical($targetMajor === $transitions[0]);
    }

    /**
     * @return list<string>
     */
    private function stepsFromCanonical(bool $applyFromStep): array
    {
        $steps = self::CANONICAL_STEPS;

        if ($applyFromStep && $this->fromStep !== null) {
            $fromIndex = array_search($this->fromStep, $steps, true);
            $steps = array_slice($steps, is_int($fromIndex) ? $fromIndex : 0);
        }

        return array_values(array_filter(
            $steps,
            fn (string $step): bool => ! in_array($step, $this->skipSteps, true),
        ));
    }

    public function isNoOp(): bool
    {
        return $this->currentMajor === $this->targetMajor;
    }

    public function isPlanMode(): bool
    {
        return $this->planMode;
    }

    /**
     * @return list<string>
     */
    public function transitionLabels(): array
    {
        $labels = [];
        $from = $this->currentMajor;

        foreach ($this->transitions() as $to) {
            $labels[] = self::transitionLabel($from, $to);
            $from = $to;
        }

        return $labels;
    }

    public static function transitionLabel(int $from, int $to): string
    {
        return $from.'->'.$to;
    }

    /**
     * @param  string|list<string>  $skipSteps
     * @return list<string>
     */
    private function normalizeStepList(string|array $skipSteps): array
    {
        $rawSteps = is_string($skipSteps) ? explode(',', $skipSteps) : $skipSteps;
        $normalized = [];

        if (is_string($skipSteps) && trim($skipSteps) === '') {
            return [];
        }

        foreach ($rawSteps as $step) {
            if (! is_string($step)) {
                throw new InvalidArgumentException('Upgrade step names must be strings.');
            }

            $step = trim($step);

            if ($step === '') {
                throw new InvalidArgumentException('Upgrade step names cannot be empty.');
            }

            if (! in_array($step, self::CANONICAL_STEPS, true)) {
                throw new InvalidArgumentException(sprintf('Unknown upgrade step "%s".', $step));
            }

            if (! in_array($step, $normalized, true)) {
                $normalized[] = $step;
            }
        }

        return $normalized;
    }
}
