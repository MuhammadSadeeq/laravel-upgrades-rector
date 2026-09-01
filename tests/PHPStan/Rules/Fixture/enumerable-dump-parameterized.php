<?php

namespace App\EnumerableFixtures;

use Illuminate\Support\Enumerable;

abstract class ParameterizedEnumerable implements Enumerable
{
    public function dump($format = 'array')
    {
        return $this;
    }
}
