<?php // @loads

namespace App\Auth;

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User implements MustVerifyEmail
{
    public function hasVerifiedEmail()
    {
    }

    public function markEmailAsVerified()
    {
    }

    public function sendEmailVerificationNotification()
    {
    }

    public function getEmailForVerification()
    {
    }
}

?>
-----
<?php // @loads

namespace App\Auth;

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User implements MustVerifyEmail
{
    public function hasVerifiedEmail()
    {
    }

    public function markEmailAsVerified()
    {
    }

    public function sendEmailVerificationNotification()
    {
    }

    public function getEmailForVerification()
    {
    }
    public function markEmailAsUnverified(): bool
    {
        // TODO: Laravel 13 — implement markEmailAsUnverified() to satisfy the updated contract.
        return false;
    }
}

?>
