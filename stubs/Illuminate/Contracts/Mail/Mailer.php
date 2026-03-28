<?php

namespace Illuminate\Contracts\Mail;

interface Mailer
{
    public function raw(string $text, mixed $callback): ?SentMessage;

    public function send(Mailable|string|array $view, array $data = [], ?callable $callback = null): ?SentMessage;

    public function sendNow(Mailable|string|array $mailable, array $data = [], ?callable $callback = null): ?SentMessage;
}
