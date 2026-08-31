<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Support\Js;

final class UnrelatedJs
{
    public static function from(mixed $value): mixed
    {
        return $value;
    }
}

function renderUnrelatedJs(mixed $payload): mixed
{
    return UnrelatedJs::from($payload);
}

function renderOtherSupportMethod(mixed $payload): mixed
{
    return Js::toHtml($payload);
}
