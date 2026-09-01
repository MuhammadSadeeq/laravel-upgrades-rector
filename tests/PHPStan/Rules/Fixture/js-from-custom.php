<?php

namespace App\Laravel13RuleFixtures\CustomSupport;

final class Js
{
    public static function from(mixed $value): mixed
    {
        return $value;
    }
}

function renderCustomJs(mixed $payload): mixed
{
    return Js::from($payload);
}
