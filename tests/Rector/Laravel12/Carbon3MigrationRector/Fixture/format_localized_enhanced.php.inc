<?php

use Carbon\Carbon;

function testFormatLocalizedEnhanced()
{
    $date = Carbon::now();

    // Should convert both method and format string
    $formatted1 = $date->formatLocalized('%A %d %B %Y');
    $formatted2 = $date->formatLocalized('%Y-%m-%d %H:%M:%S');
    $formatted3 = $date->formatLocalized('%a, %b %e');

    return [$formatted1, $formatted2, $formatted3];
}

?>
-----
<?php

use Carbon\Carbon;

function testFormatLocalizedEnhanced()
{
    $date = Carbon::now();

    // Should convert both method and format string
    $formatted1 = $date->isoFormat('dddd DD MMMM YYYY');
    $formatted2 = $date->isoFormat('YYYY-MM-DD HH:mm:ss');
    $formatted3 = $date->isoFormat('ddd, MMM D');

    return [$formatted1, $formatted2, $formatted3];
}

?>