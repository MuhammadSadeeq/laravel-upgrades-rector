<?php

use Illuminate\Cache\RateLimiting\Limit;

class ApiRateLimiter
{
    public function handle()
    {
        return Limit::perMinute(60, 5);
    }

    public function basic()
    {
        return new Limit('api', 100, 10);
    }
}

?>
-----
<?php

use Illuminate\Cache\RateLimiting\Limit;

class ApiRateLimiter
{
    public function handle()
    {
        return Limit::perMinute(60, 5 * 60);
    }

    public function basic()
    {
        return new Limit('api', 100, 10 * 60);
    }
}

?>