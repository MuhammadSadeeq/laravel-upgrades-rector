<?php

use Illuminate\Foundation\Configuration\ApplicationBuilder;

function acceptScheduleBuilder(ApplicationBuilder $builder): ApplicationBuilder
{
    return $builder->withSchedule(static fn (): null => null);
}

function scheduleAsArgument(ApplicationBuilder $builder): void
{
    acceptScheduleBuilder($builder->withSchedule(static fn (): null => null));
}
