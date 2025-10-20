<?php

use Illuminate\Contracts\Mail\Mailer;
use SomeOther\Interface as SomeInterface;

class MultipleInterfacesMailer implements SomeInterface, Mailer
{
    public function to($users)
    {
        // implementation
    }

    public function send($view, array $data = [], $callback = null)
    {
        // implementation
    }

    public function someInterfaceMethod()
    {
        // implementation for other interface
    }
}

?>
-----
<?php

use Illuminate\Contracts\Mail\Mailer;
use SomeOther\Interface as SomeInterface;

/** Laravel 11: Mailer contract requires new sendNow method. Add: public function sendNow($mailable, array $data = [], $callback = null); */
class MultipleInterfacesMailer implements SomeInterface, Mailer
{
    public function to($users)
    {
        // implementation
    }

    public function send($view, array $data = [], $callback = null)
    {
        // implementation
    }

    public function someInterfaceMethod()
    {
        // implementation for other interface
    }
}

?>