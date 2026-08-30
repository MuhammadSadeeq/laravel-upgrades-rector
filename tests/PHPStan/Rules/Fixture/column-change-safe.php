<?php

namespace App;

final class UnrelatedColumnBuilder
{
    public function change(): void {}
}

function leaveUnrelatedColumn(UnrelatedColumnBuilder $builder): void
{
    $builder->change();
}
