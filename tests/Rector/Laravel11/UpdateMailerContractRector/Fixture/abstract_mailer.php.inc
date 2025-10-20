<?php

use Illuminate\Contracts\Mail\Mailer;

abstract class AbstractMailer implements Mailer
{
    public function to($users)
    {
        // base implementation
    }

    public function send($view, array $data = [], $callback = null)
    {
        // base implementation
    }

    abstract public function queue($view, array $data = [], $callback = null);
}

?>
-----
<?php

use Illuminate\Contracts\Mail\Mailer;

/** Laravel 11: Mailer contract requires new sendNow method. Add: public function sendNow($mailable, array $data = [], $callback = null); */
abstract class AbstractMailer implements Mailer
{
    public function to($users)
    {
        // base implementation
    }

    public function send($view, array $data = [], $callback = null)
    {
        // base implementation
    }

    abstract public function queue($view, array $data = [], $callback = null);
}

?>