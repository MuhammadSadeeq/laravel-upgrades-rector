<?php

namespace App;

use Illuminate\Support\Facades\Concurrency;

function concurrencyIndexedResults(): array
{
    return Concurrency::run([
        static fn (): int => 1,
        static fn (): int => 2,
    ]);
}
