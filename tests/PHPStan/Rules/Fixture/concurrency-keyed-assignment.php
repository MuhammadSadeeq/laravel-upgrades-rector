<?php

namespace App;

use Illuminate\Support\Facades\Concurrency;

function concurrencyKeyedAssignment(): void
{
    $results = Concurrency::run([
        'first' => static fn (): int => 1,
    ]);
}
