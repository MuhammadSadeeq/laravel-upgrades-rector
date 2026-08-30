<?php

namespace App;

use Carbon\CarbonImmutable;

function freezeImmutable(): void
{
    $now = CarbonImmutable::create(2024, 1, 1);
    CarbonImmutable::setTestNow($now);
}
