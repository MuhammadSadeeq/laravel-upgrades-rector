<?php

namespace App\EnumerableFixtures;

use Illuminate\Support\Enumerable;

abstract class VariadicEnumerable implements Enumerable
{
    public function dump(...$args)
    {
        return $this;
    }
}
