<?php

use Carbon\Carbon;

function testIsSameMethods()
{
    $date = Carbon::now();
    $other = Carbon::now()->addDay();

    // Should change to isCurrent* when no arguments
    $sameDay = $date->isSameDay();
    $sameMonth = $date->isSameMonth();

    // Should not change - has arguments
    $sameWithOther = $date->isSameDay($other);

    return [$sameDay, $sameMonth, $sameWithOther];
}

?>
-----
<?php

use Carbon\Carbon;

function testIsSameMethods()
{
    $date = Carbon::now();
    $other = Carbon::now()->addDay();

    // Should change to isCurrent* when no arguments
    $sameDay = $date->isCurrentDay();
    $sameMonth = $date->isCurrentMonth();

    // Should not change - has arguments
    $sameWithOther = $date->isSameDay($other);

    return [$sameDay, $sameMonth, $sameWithOther];
}

?>