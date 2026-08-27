<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use InvalidArgumentException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;

/**
 * Immutable dependencies and run options shared by upgrade steps.
 */
class UpgradeContext
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $workingDirectory,
        public readonly UpgradePlan $plan,
        public readonly string $runId,
        public readonly array $options = [],
        ?int $activeFromMajor = null,
        ?int $activeToMajor = null,
    ) {
        if ($workingDirectory === '') {
            throw new InvalidArgumentException('An upgrade working directory is required.');
        }

        if ($runId === '') {
            throw new InvalidArgumentException('An upgrade run id is required.');
        }

        $this->activeFromMajor = $activeFromMajor ?? $plan->currentMajor;
        $this->activeToMajor = $activeToMajor ?? $plan->targetMajor;

        if (($activeFromMajor !== null || $activeToMajor !== null)
            && $this->activeToMajor !== $this->activeFromMajor + 1) {
            throw new InvalidArgumentException('An active upgrade transition must advance exactly one Laravel major.');
        }
    }

    public readonly int $activeFromMajor;

    public readonly int $activeToMajor;

    public function isPlanMode(): bool
    {
        return $this->plan->isPlanMode();
    }

    public function currentMajor(): int
    {
        return $this->plan->currentMajor;
    }

    public function targetMajor(): int
    {
        return $this->plan->targetMajor;
    }

    public function fromMajor(): int
    {
        return $this->activeFromMajor;
    }

    public function toMajor(): int
    {
        return $this->activeToMajor;
    }

    /**
     * @return array{from: int, to: int}
     */
    public function activeTransition(): array
    {
        return ['from' => $this->activeFromMajor, 'to' => $this->activeToMajor];
    }

    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }
}
