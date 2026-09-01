<?php

namespace App\Support;

/**
 * Application-owned Js helper. It must not be confused with
 * Illuminate\Support\Js by the Laravel 13 advisory.
 */
final class Js
{
    public static function from(mixed $value): string
    {
        return (string) json_encode($value);
    }
}
