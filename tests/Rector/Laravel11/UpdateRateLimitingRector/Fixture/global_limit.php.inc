<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

class RateLimitConfig
{
    public function configure()
    {
        return new \Illuminate\Cache\RateLimiting\GlobalLimit(100, 2);
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

class RateLimitConfig
{
    public function configure()
    {
        return new \Illuminate\Cache\RateLimiting\GlobalLimit(100, 2 * 60);
    }
}

?>
