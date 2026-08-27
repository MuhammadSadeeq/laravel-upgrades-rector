<?php

function inspectArrayFirstLastCallbacks(array $items): void
{
    array_first($items, static fn (mixed $item): bool => (bool) $item);
    \array_last($items, static fn (mixed $item): bool => (bool) $item);
}
