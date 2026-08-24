<?php

namespace LoadGateNegative;

use Illuminate\Contracts\Cache\Store;

class NarrowedCache implements Store
{
    public function touch(string $key, int $seconds)
    {
    }
}
