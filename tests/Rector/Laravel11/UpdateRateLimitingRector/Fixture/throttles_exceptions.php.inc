<?php

use Illuminate\Queue\Middleware\ThrottlesExceptions;

class JobWithThrottle
{
    public function middleware()
    {
        return [
            new ThrottlesExceptions(10, 5),
        ];
    }
}

?>
-----
<?php

use Illuminate\Queue\Middleware\ThrottlesExceptions;

class JobWithThrottle
{
    public function middleware()
    {
        return [
            new ThrottlesExceptions(10, 5 * 60),
        ];
    }
}

?>