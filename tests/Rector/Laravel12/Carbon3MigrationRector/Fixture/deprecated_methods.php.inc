<?php

use Carbon\Carbon;

function testDeprecatedMethods()
{
    $date = Carbon::now();

    // formatLocalized should become isoFormat with converted format string
    $formatted = $date->formatLocalized('%A');

    // setUtf8 should be removed
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

    // formatLocalized should become isoFormat with converted format string
    $formatted = $date->isoFormat('dddd');

    // setUtf8 should be removed
    $date;

    // TODO: Carbon 3 removed setWeekStartsAt(). Configure week start/end via CarbonImmutable::setWeekStartsAt() or locale settings.
    $date;
    // TODO: Carbon 3 removed setWeekEndsAt(). Configure week start/end via CarbonImmutable::setWeekStartsAt() or locale settings.
    $date;

    return $formatted;
}

?>
