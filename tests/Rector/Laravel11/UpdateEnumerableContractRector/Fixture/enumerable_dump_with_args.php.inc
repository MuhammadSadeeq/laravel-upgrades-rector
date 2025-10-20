<?php

use Illuminate\Support\Enumerable;

class AdvancedCollection implements Enumerable
{
    public function dump(...$args)
    {
        // Already has variadic arguments - should not be changed
        dd($this->toArray(), ...$args);
    }

    public function toArray()
    {
        return ['items' => [1, 2, 3]];
    }
}

?>