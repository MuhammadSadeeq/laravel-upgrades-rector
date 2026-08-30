<?php

namespace App;

use Carbon\CarbonInterface;

function humanDateWithOptions(CarbonInterface $date): string
{
    return $date->diffForHumans(null, true, 2);
}
