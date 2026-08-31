<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Notifications\Messages\MailMessage;

function subjectInArray(MailMessage $message): array
{
    return [$message->subject('Reset Password Notification'), 'Reset your password'];
}

function subjectInReturn(): string
{
    return 'Reset Password Notification';
}
