<?php

namespace App\Mail;

use Illuminate\Contracts\Mail\Mailer;

class CustomMailer implements Mailer
{
}

?>
-----
<?php

namespace App\Mail;

use Illuminate\Contracts\Mail\Mailer;

class CustomMailer implements Mailer
{
    public function sendNow($mailable, array $data = [], $callback = null): ?\Illuminate\Mail\SentMessage
    {
        // TODO: Laravel 11 — implement sendNow() to satisfy the updated contract.
        return null;
    }
}

?>
