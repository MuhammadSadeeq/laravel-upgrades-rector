<?php

namespace App\Support;

final class JsPayload
{
    public static function render(mixed $payload): string
    {
        return Js::from($payload);
    }
}
