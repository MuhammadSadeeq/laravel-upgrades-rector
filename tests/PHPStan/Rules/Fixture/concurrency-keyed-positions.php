<?php

namespace App;

use Illuminate\Support\Facades\Concurrency;

function consumeConcurrencyResults(array $results): void {}

function concurrencyKeyedPositions(): array
{
    consumeConcurrencyResults(Concurrency::run([
        'argument' => static fn (): int => 1,
    ]));

    return Concurrency::run([
        'return' => static fn (): int => 2,
    ]);
}
