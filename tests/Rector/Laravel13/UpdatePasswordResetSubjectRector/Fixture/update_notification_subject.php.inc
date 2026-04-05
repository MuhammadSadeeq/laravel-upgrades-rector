<?php

use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function toMail(object $notifiable)
    {
        return (new MailMessage())->subject('Reset Password Notification');
    }
}

?>
-----
<?php

use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function toMail(object $notifiable)
    {
        return (new MailMessage())->subject('Reset your password');
    }
}

?>
