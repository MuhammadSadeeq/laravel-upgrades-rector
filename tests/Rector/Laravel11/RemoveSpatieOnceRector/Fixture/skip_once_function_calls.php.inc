<?php

namespace App\Fixtures\Laravel11\RemoveSpatieOnceRector;

use Illuminate\Support\Facades\Cache;

class NoSpatieImports
{
    public function example()
    {
        $result = once(fn() => expensive());
    }
}

?>
