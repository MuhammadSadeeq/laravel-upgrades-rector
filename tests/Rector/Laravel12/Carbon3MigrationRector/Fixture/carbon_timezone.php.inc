<?php

use Carbon\CarbonTimeZone;

function testCarbonTimeZone()
{
    // Should add UTC parameter
    $timezone1 = new CarbonTimeZone();

    // Should not change - already has parameter
    $timezone2 = new CarbonTimeZone('America/New_York');

    return [$timezone1, $timezone2];
}

?>
-----
<?php

use Carbon\CarbonTimeZone;

function testCarbonTimeZone()
{
    // Should add UTC parameter
    $timezone1 = new CarbonTimeZone('UTC');

    // Should not change - already has parameter
    $timezone2 = new CarbonTimeZone('America/New_York');

    return [$timezone1, $timezone2];
}

?>