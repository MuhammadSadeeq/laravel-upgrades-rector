<?php

use Illuminate\Foundation\Configuration\ApplicationBuilder;

function registerLegacySchedule(ApplicationBuilder $builder): void
{
    $builder->withScheduling(static fn (): null => null);
}

class CustomScheduleBuilder
{
    public function withSchedule(callable $callback): void {}
}
