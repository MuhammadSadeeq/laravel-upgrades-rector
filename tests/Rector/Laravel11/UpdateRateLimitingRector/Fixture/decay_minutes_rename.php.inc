<?php

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

use Illuminate\Queue\Middleware\ThrottlesExceptions;

class ThrottleJob extends ThrottlesExceptions
{
    public function handle()
    {
        $delay = $this->decaySeconds;
    }
}

?>
