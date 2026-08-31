<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Support\Js;

function renderQualifiedJs(mixed $payload): mixed
{
    return consumeJs(Js::from($payload));
}

function consumeJs(mixed $value): mixed
{
    return $value;
}

function renderQualifiedJsAgain(mixed $payload): mixed
{
    return Js::from($payload);
}
