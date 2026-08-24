<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

/**
 * One computed decision about a single dependency for a target Laravel major.
 */
final class DependencyDecision
{
    public const ACTION_BUMP = 'bump';

    public const ACTION_KEEP = 'keep';

    public const ACTION_REMOVE = 'remove';

    public const ACTION_UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $package,
        public readonly string $section,
        public readonly ?string $current,
        public readonly ?string $proposed,
        public readonly string $action,
        public readonly string $reason,
    ) {}

    /**
     * @return array{package: string, section: string, current: string|null, proposed: string|null, action: string, reason: string}
     */
    public function toArray(): array
    {
        return [
            'package' => $this->package,
            'section' => $this->section,
            'current' => $this->current,
            'proposed' => $this->proposed,
            'action' => $this->action,
            'reason' => $this->reason,
        ];
    }
}
