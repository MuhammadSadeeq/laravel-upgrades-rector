<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Support\Str;

function useRegularStringMethods(): string
{
    return Str::upper(Str::random(8));
}

final class StrFactoryUnrelatedService
{
    public static function create(): mixed
    {
        return null;
    }
}

function unrelatedCreateCall(): mixed
{
    return StrFactoryUnrelatedService::create();
}
