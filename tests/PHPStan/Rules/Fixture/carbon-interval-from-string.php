<?php

namespace App;

use Carbon\CarbonInterval;

function parseFractionalInterval(): CarbonInterval
{
    return CarbonInterval::fromString('1.5 hours');
}
