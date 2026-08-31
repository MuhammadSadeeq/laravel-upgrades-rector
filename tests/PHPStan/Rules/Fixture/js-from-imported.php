<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Support\Js;

function renderImportedJs(mixed $payload): Js
{
    return Js::from($payload);
}
