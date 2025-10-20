<?php

use Illuminate\Cache\RateLimiting\GlobalLimit;

class RateLimitConfig
{
    public function configure()
    {
        return new GlobalLimit(100, 2);
    }
}

?>
-----
<?php

use Illuminate\Cache\RateLimiting\GlobalLimit;

class RateLimitConfig
{
    public function configure()
    {
        return new GlobalLimit(100, 2 * 60);
    }
}

?>