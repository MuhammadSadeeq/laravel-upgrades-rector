<?php

use Carbon\Carbon;

function testSignedDiffMethods()
{
    $start = Carbon::now();
    $end = Carbon::now()->addHours(2);

    return [
        'days' => $end->diffInDays($start, false),
        'hours' => $end->diffInHours($start, absolute: false),
    ];
}

?>
-----
<?php

use Carbon\Carbon;

function testSignedDiffMethods()
{
    $start = Carbon::now();
    $end = Carbon::now()->addHours(2);

    return [
        'days' => (int) $end->diffInDays($start),
        'hours' => (int) $end->diffInHours($start),
    ];
}

?>
