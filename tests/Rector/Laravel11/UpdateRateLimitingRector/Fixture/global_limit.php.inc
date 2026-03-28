<?php

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

class RateLimitConfig
{
    public function configure()
    {
        return new \Illuminate\Cache\RateLimiting\GlobalLimit(100, 2 * 60);
    }
}

?>
