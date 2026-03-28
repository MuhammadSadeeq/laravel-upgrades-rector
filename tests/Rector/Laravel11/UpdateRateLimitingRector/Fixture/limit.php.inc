<?php

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

class ApiRateLimiter
{
    public function basic()
    {
        return new \Illuminate\Cache\RateLimiting\Limit('api', 100, 10 * 60);
    }
}

?>
