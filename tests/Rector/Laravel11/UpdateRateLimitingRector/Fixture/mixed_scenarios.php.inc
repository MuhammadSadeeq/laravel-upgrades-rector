<?php

use Illuminate\Queue\Middleware\ThrottlesExceptions;

class MixedRateLimiter extends ThrottlesExceptions
{
    public function configure()
    {
        $global = new \Illuminate\Cache\RateLimiting\GlobalLimit(100, 2);
        $limit = new \Illuminate\Cache\RateLimiting\Limit('api', 60, 5);
        $delay = $this->decayMinutes;
        $alreadyConverted = new \Illuminate\Cache\RateLimiting\GlobalLimit(100, 2 * 60);
    }
}

?>
-----
<?php

use Illuminate\Queue\Middleware\ThrottlesExceptions;

class MixedRateLimiter extends ThrottlesExceptions
{
    public function configure()
    {
        $global = new \Illuminate\Cache\RateLimiting\GlobalLimit(100, 2 * 60);
        $limit = new \Illuminate\Cache\RateLimiting\Limit('api', 60, 5 * 60);
        $delay = $this->decaySeconds;
        $alreadyConverted = new \Illuminate\Cache\RateLimiting\GlobalLimit(100, 2 * 60);
    }
}

?>
