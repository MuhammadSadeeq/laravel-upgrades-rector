<?php

namespace App\Fixtures\Laravel11\UpdateEnumerableContractRector;

use Illuminate\Support\Enumerable;

class AdvancedCollection implements Enumerable
{
    public function dump(...$args)
    {
        dd($this->toArray(), ...$args);
    }

    public function toArray()
    {
        return ['items' => [1, 2, 3]];
    }
}

?>
