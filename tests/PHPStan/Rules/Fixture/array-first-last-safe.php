<?php

namespace App\ArrayHelpers;

function array_first(array $items): mixed
{
    return $items[0] ?? null;
}

function inspectArrayFirstLastSafe(array $items): void
{
    array_first($items);

    \App\ArrayHelpers\array_first($items, static fn (mixed $item): bool => (bool) $item);
}
