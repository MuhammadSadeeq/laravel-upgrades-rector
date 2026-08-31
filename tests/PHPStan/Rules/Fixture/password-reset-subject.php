<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Notifications\Messages\MailMessage;

function passwordResetSubject(MailMessage $message): mixed
{
    return consumePasswordSubject($message->subject('Reset Password Notification'));
}

function consumePasswordSubject(mixed $subject): mixed
{
    return $subject;
}
