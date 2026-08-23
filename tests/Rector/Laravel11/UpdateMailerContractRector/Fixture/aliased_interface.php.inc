<?php

namespace App\Mail;

use Illuminate\Contracts\Mail\Mailer as MailerContract;

class LegacyMailer implements MailerContract
{
}

?>
-----
<?php

namespace App\Mail;

use Illuminate\Contracts\Mail\Mailer as MailerContract;

class LegacyMailer implements MailerContract
{
    public function sendNow($mailable, array $data = [], $callback = null): ?\Illuminate\Mail\SentMessage
    {
        // TODO: Laravel 11 — implement sendNow() to satisfy the updated contract.
        return null;
    }
}

?>
