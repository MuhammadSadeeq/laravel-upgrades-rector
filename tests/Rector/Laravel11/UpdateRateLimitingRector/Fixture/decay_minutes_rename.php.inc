<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

use Illuminate\Queue\Middleware\ThrottlesExceptions;

class ThrottleJob extends ThrottlesExceptions
{
    public function handle()
    {
        $delay = $this->decayMinutes;
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

use Illuminate\Queue\Middleware\ThrottlesExceptions;

class ThrottleJob extends ThrottlesExceptions
{
    public function handle()
    {
        $delay = $this->decaySeconds;
    }
}

?>
