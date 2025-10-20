<?php

use Carbon\Carbon;

function testMinMaxValue()
{
    $min = Carbon::minValue();
    $max = Carbon::maxValue();

    return [$min, $max];
}

?>
-----
<?php

use Carbon\Carbon;

function testMinMaxValue()
{
    $min = \Carbon\CarbonImmutable::startOfTime();
    $max = \Carbon\CarbonImmutable::endOfTime();

    return [$min, $max];
}

?>