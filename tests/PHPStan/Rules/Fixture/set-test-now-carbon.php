<?php

namespace App;

use Carbon\Carbon;

function freezeCarbon(): void
{
    $now = Carbon::create(2024, 1, 1);
    Carbon::setTestNow($now);
}
