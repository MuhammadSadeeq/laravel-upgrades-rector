<?php

use Carbon\Carbon;

function testCreateFromTimestamp()
{
    // Should change to createFromTimestampUTC
    $carbon1 = Carbon::createFromTimestamp(1234567890);

    // Should not change - already has timezone
    $carbon2 = Carbon::createFromTimestamp(1234567890, 'America/New_York');

    return [$carbon1, $carbon2];
}

?>
-----
<?php

use Carbon\Carbon;

function testCreateFromTimestamp()
{
    // Should change to createFromTimestampUTC
    $carbon1 = Carbon::createFromTimestampUTC(1234567890);

    // Should not change - already has timezone
    $carbon2 = Carbon::createFromTimestamp(1234567890, 'America/New_York');

    return [$carbon1, $carbon2];
}

?>