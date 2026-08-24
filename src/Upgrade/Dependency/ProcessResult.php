<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

final class ProcessResult
{
    public function __construct(
        public readonly string $command,
        public readonly int $exitCode,
        public readonly string $output,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
