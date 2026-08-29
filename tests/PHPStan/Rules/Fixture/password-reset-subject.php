<?php

final class PasswordResetNotification
{
    public function subject(string $subject): void {}
}

function passwordResetSubject(): void
{
    (new PasswordResetNotification)->subject('Reset Password');
}
