<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Support\Str;

function configureRandomStringFactory(): void
{
    Str::createRandomStringsUsing(static fn (int $length): string => str_repeat('x', $length));
}
