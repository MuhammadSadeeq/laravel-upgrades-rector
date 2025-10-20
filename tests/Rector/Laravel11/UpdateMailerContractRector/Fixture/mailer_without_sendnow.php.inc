<?php

use Illuminate\Contracts\Mail\Mailer;

class CustomMailer implements Mailer
{
    public function to($users)
    {
        // implementation
    }

    public function send($view, array $data = [], $callback = null)
    {
        // implementation
    }

    public function queue($view, array $data = [], $callback = null)
    {
        // implementation
    }
}

?>
-----
<?php

use Illuminate\Contracts\Mail\Mailer;

/** Laravel 11: Mailer contract requires new sendNow method. Add: public function sendNow($mailable, array $data = [], $callback = null); */
class CustomMailer implements Mailer
{
    public function to($users)
    {
        // implementation
    }

    public function send($view, array $data = [], $callback = null)
    {
        // implementation
    }

    public function queue($view, array $data = [], $callback = null)
    {
        // implementation
    }
}

?>