<?php

use Illuminate\Cache\RateLimiting\GlobalLimit;
use Illuminate\Cache\RateLimiting\Limit;

class AlreadyUpdated
{
    public function configure()
    {
        return new GlobalLimit(100, 7200);
    }

    public function limit()
    {
        return new Limit('api', 100, 3600);
    }
}

?>