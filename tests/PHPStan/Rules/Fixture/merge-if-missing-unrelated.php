<?php

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules\Fixture;

final class MergeIfMissingUnrelated
{
    /** @param array<string, mixed> $values */
    public function mergeIfMissing(array $values): void {}
}

function mergeUnrelatedValues(MergeIfMissingUnrelated $bag): void
{
    $bag->mergeIfMissing([
        'user.last_name' => 'Otwell',
    ]);
}
