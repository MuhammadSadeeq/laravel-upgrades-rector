<?php

namespace App\Mail;

use Illuminate\Contracts\Mail\Mailer;

class CustomMailer implements Mailer
{
    public function sendNow($mailable, array $data = [], $callback = null): void
    {
    }
}

?>
