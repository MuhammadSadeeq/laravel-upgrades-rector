<?php // @loads

namespace App\Mail;

use Illuminate\Contracts\Mail\Mailer as MailerContract;

class LegacyMailer implements MailerContract
{
    public function to($users)
    {
    }

    public function bcc($users)
    {
    }

    public function raw($text, $callback)
    {
    }

    public function send($view, array $data = [], $callback = null)
    {
    }
}

?>
-----
<?php // @loads

namespace App\Mail;

use Illuminate\Contracts\Mail\Mailer as MailerContract;

class LegacyMailer implements MailerContract
{
    public function to($users)
    {
    }

    public function bcc($users)
    {
    }

    public function raw($text, $callback)
    {
    }

    public function send($view, array $data = [], $callback = null)
    {
    }
    public function sendNow($mailable, array $data = [], $callback = null): ?\Illuminate\Mail\SentMessage
    {
        // TODO: Laravel 11 — implement sendNow() to satisfy the updated contract.
        return null;
    }
}

?>
