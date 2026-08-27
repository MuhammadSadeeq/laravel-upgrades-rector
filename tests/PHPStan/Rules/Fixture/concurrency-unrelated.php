<?php

namespace App;

class Concurrency
{
    /** @param array<string, callable> $tasks */
    public static function run(array $tasks): array
    {
        return $tasks;
    }
}

function unrelatedConcurrency(): void
{
    Concurrency::run(['first' => static fn (): int => 1]);
}
