<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process;

/** Result of an external argv-based process. */
final class ProcessResult
{
    /**
     * @param  list<string>  $arguments
     */
    public function __construct(
        public readonly array $arguments,
        public readonly int $exitCode,
        public readonly string $output = '',
        public readonly string $errorOutput = '',
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function isSuccess(): bool
    {
        return $this->isSuccessful();
    }

    public function combinedOutput(): string
    {
        if ($this->output === '' || $this->errorOutput === '') {
            return $this->output.$this->errorOutput;
        }

        $separator = str_ends_with($this->output, "\n") || str_starts_with($this->errorOutput, "\n")
            ? ''
            : "\n";

        return $this->output.$separator.$this->errorOutput;
    }
}
