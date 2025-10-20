<?php

use Carbon\Carbon;

function testDeprecatedMethods()
{
    $date = Carbon::now();

    // formatLocalized should become isoFormat
    $formatted = $date->formatLocalized('%A');

    // These methods should be removed, returning the object
    $date->setUtf8();
    $date->setWeekStartsAt(1);
    $date->setWeekEndsAt(0);

    return $formatted;
}

?>
-----
<?php

use Carbon\Carbon;

function testDeprecatedMethods()
{
    $date = Carbon::now();

    // formatLocalized should become isoFormat
    $formatted = $date->isoFormat('%A');

    // These methods should be removed, returning the object
    $date;
    $date;
    $date;

    return $formatted;
}

?>