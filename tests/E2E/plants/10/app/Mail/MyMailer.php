<?php

namespace App\Mail;

use Illuminate\Contracts\Mail\Mailer as MailerContract;

class MyMailer implements MailerContract
{
    public function to($users)
    {
        return $this;
    }

    public function bcc($users)
    {
        return $this;
    }

    public function raw($text, $callback)
    {
        return;
    }

    public function send($view, array $data = [], $callback = null)
    {
        return null;
    }
}
