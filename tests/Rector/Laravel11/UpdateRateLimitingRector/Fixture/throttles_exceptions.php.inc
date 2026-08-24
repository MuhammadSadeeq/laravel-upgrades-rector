<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

class JobWithThrottle
{
    public function middleware()
    {
        return [
            new \Illuminate\Queue\Middleware\ThrottlesExceptions(10, 5),
        ];
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

class JobWithThrottle
{
    public function middleware()
    {
        return [
            new \Illuminate\Queue\Middleware\ThrottlesExceptions(10, 5 * 60),
        ];
    }
}

?>
