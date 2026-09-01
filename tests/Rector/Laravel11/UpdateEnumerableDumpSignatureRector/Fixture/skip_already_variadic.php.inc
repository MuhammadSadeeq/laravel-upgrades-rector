<?php

namespace App\Fixtures\Laravel11\UpdateEnumerableContractRector;

use Illuminate\Support\Enumerable;

class AlreadyVariadicCollection implements Enumerable
{
    public function dump(...$args)
    {
        return $this;
    }
}
