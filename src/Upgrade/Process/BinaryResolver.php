<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process;

use Symfony\Component\Process\ExecutableFinder;

/** Resolves tool binaries without invoking a shell. */
final class BinaryResolver
{
    public function __construct(private readonly ?ExecutableFinder $finder = null) {}

    public function phpBinary(): string
    {
        return PHP_BINARY;
    }

    public function composerBinary(?string $contextOption = null): string
    {
        if (is_string($contextOption) && $contextOption !== '') {
            return $contextOption;
        }

        $environmentBinary = getenv('COMPOSER_BINARY');

        if (is_string($environmentBinary) && $environmentBinary !== '') {
            return $environmentBinary;
        }

        return ($this->finder ?? new ExecutableFinder)->find('composer', 'composer') ?? 'composer';
    }

    public function gitBinary(): string
    {
        return ($this->finder ?? new ExecutableFinder)->find('git', 'git') ?? 'git';
    }
}
