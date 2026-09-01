<?php

namespace App\Support;

final class LegacyArrayLookup
{
    public static function first(array $values): mixed
    {
        return array_first($values, static fn (mixed $value): bool => $value !== null);
    }

    public static function last(array $values): mixed
    {
        return array_last($values, static fn (mixed $value): bool => $value !== null);
    }
}
