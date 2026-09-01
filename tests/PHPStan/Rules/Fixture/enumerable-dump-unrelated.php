<?php

namespace App\EnumerableFixtures;

abstract class UnrelatedEnumerable
{
    public function dump($format = 'array')
    {
        return $this;
    }
}
