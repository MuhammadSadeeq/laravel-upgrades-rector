<?php

namespace App;

use Carbon\CarbonImmutable;

function humanDate(CarbonImmutable $date): string
{
    return $date->diffForHumans();
}
