<?php

use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function toMail(object $notifiable)
    {
        return (new MailMessage())->subject('Reset your account password');
    }
}

?>
