<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

class AlreadyUpdated
{
    public function configure()
    {
        return new \Illuminate\Cache\RateLimiting\GlobalLimit(100, 2 * 60);
    }

    public function limit()
    {
        return new \Illuminate\Cache\RateLimiting\Limit('api', 100, 5 * 60);
    }
}

?>
