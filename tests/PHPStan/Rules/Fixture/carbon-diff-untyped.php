<?php

namespace App;

/** @return mixed */
function unknownDate()
{
    return null;
}

function inspectUnknownDate(): void
{
    $date = unknownDate();
    $date->diffInDays();
}
