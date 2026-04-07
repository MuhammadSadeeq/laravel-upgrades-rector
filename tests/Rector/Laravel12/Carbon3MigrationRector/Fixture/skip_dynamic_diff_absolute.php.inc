<?php

use Carbon\Carbon;

function testDynamicDiffAbsolute(bool $absolute)
{
    $start = Carbon::now();
    $end = Carbon::now()->addHours(2);

    return $end->diffInHours($start, $absolute);
}

?>
