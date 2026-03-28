<?php

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
