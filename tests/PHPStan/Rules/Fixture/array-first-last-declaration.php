<?php

function array_first(array $items)
{
    return $items[0] ?? null;
}

function array_last(array $items)
{
    return $items[count($items) - 1] ?? null;
}
