<?php

use Illuminate\Contracts\Mail\Mailer;

class CompleteMailer implements Mailer
{
    public function to($users)
    {
        // implementation
    }

    public function send($view, array $data = [], $callback = null)
    {
        // implementation
    }

    public function sendNow($mailable, array $data = [], $callback = null)
    {
        // implementation - already has the required method
    }

    public function queue($view, array $data = [], $callback = null)
    {
        // implementation
    }
}

?>