<?php

namespace App\Fixtures\Laravel13\UpdatePasswordResetSubjectRector;

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

namespace App\Fixtures\Laravel13\UpdatePasswordResetSubjectRector;

use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function toMail(object $notifiable)
    {
        return (new MailMessage())->subject('Reset your password');
    }
}

?>
