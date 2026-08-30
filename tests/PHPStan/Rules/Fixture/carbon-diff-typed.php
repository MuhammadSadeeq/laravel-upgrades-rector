<?php

namespace App;

use Carbon\CarbonImmutable;

function inspectCarbon(CarbonImmutable $date): void
{
    $date->diffInDays();
}
