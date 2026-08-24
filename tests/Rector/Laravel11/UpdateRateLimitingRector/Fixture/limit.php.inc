<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

class ApiRateLimiter
{
    public function basic()
    {
        return new \Illuminate\Cache\RateLimiting\Limit('api', 100, 10);
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

class ApiRateLimiter
{
    public function basic()
    {
        return new \Illuminate\Cache\RateLimiting\Limit('api', 100, 10 * 60);
    }
}

?>
