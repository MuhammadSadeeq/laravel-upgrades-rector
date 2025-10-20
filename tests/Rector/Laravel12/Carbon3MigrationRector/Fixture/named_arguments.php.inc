<?php

use Carbon\Carbon;

function testNamedArguments()
{
    // Should change tz to timezone
    $date1 = Carbon::create(2023, 1, 1, 12, 0, 0, tz: 'UTC');
    $date2 = Carbon::createFromFormat('Y-m-d', '2023-01-01', tz: 'America/New_York');

    // Should not change - already uses timezone
    $date3 = Carbon::create(2023, 1, 1, timezone: 'UTC');

    return [$date1, $date2, $date3];
}

?>
-----
<?php

use Carbon\Carbon;

function testNamedArguments()
{
    // Should change tz to timezone
    $date1 = Carbon::create(2023, 1, 1, 12, 0, 0, timezone: 'UTC');
    $date2 = Carbon::createFromFormat('Y-m-d', '2023-01-01', timezone: 'America/New_York');

    // Should not change - already uses timezone
    $date3 = Carbon::create(2023, 1, 1, timezone: 'UTC');

    return [$date1, $date2, $date3];
}

?>