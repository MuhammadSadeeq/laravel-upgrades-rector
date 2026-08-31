<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Notifications\Messages\MailMessage;

function newPasswordSubject(MailMessage $message): mixed
{
    return $message->subject('Reset your password');
}

function unrelatedPasswordText(): string
{
    return 'Reset Password';
}
