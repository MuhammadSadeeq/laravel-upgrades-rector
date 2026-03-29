<?php

namespace Illuminate\Contracts\Auth;

interface MustVerifyEmail
{
    public function hasVerifiedEmail(): bool;

    public function markEmailAsVerified(): bool;

    public function sendEmailVerificationNotification(): void;

    public function getEmailForVerification(): string;
}
