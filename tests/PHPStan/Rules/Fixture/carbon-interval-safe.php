<?php

namespace App;

use Carbon\Carbon;

function parseDate(): Carbon
{
    return Carbon::create(2024, 1, 1);
}

function makeDateInterval(): \DateInterval
{
    return \DateInterval::createFromDateString('1 day');
}
