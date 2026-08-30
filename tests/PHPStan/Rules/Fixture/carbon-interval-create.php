<?php

namespace App;

use Carbon\CarbonInterval;

function createFractionalInterval(): CarbonInterval
{
    return CarbonInterval::create(0, 0, 0, 0, 1.5);
}
