<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process;

use InvalidArgumentException;

/**
 * An argv-based external process request.
 *
 * Keeping arguments as an argv list is deliberate: upgrade steps never need
 * to construct a shell command or rely on shell quoting rules.
 */
final class ProcessRequest
{
    /**
     * @param  list<string>  $arguments
     */
    public function __construct(
        public readonly array $arguments,
        public readonly string $workingDirectory,
        public readonly ?float $timeout = 300.0,
    ) {
        if ($arguments === [] || $arguments[0] === '') {
            throw new InvalidArgumentException('A process request needs an executable.');
        }

        if ($workingDirectory === '') {
            throw new InvalidArgumentException('A process request needs a working directory.');
        }
    }

    public function executable(): string
    {
        return $this->arguments[0];
    }

    /**
     * @return list<string>
     */
    public function commandArguments(): array
    {
        return array_slice($this->arguments, 1);
    }
}
