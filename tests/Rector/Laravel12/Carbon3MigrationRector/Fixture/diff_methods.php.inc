<?php

use Carbon\Carbon;

function testDiffMethods()
{
    $start = Carbon::now();
    $end = Carbon::now()->addHours(2);

    return [
        'seconds' => $end->diffInSeconds($start),
        'days' => $end->diffInDays($start),
        'hours' => $end->diffInHours($start),
    ];
}

?>
-----
<?php

use Carbon\Carbon;

function testDiffMethods()
{
    $start = Carbon::now();
    $end = Carbon::now()->addHours(2);

    return [
        'seconds' => (int) abs($end->diffInSeconds($start)),
        'days' => (int) abs($end->diffInDays($start)),
        'hours' => (int) abs($end->diffInHours($start)),
    ];
}

?>