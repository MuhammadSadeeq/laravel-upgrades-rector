<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

final class NumericBuilder
{
    public function float(string $column, int $total, int $places): void {}
}

function addFullPrecisionFloat(Blueprint $table): void
{
    $table->float('ratio');
}

function addUnrelatedFloat(NumericBuilder $builder): void
{
    $builder->float('ratio', 8, 2);
}
