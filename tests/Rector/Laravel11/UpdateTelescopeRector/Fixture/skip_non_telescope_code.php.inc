<?php

namespace App\Fixtures\Laravel11\UpdateTelescopeRector;

class SomeService
{
    public function filter($data)
    {
        return array_filter($data);
    }

    public function tag($items)
    {
        return array_map(fn($item) => $item->tag, $items);
    }
}

?>
