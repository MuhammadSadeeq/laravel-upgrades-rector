<?php

use Illuminate\Foundation\Configuration\ApplicationBuilder;

function registerSchedule(ApplicationBuilder $builder): void
{
    $builder->withSchedule(static fn (): null => null);
}
