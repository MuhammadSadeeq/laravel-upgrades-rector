<?php

use Illuminate\Support\Facades\Cache;

class NoSpatieImports
{
    public function example()
    {
        $result = once(fn() => expensive());
    }
}

?>
