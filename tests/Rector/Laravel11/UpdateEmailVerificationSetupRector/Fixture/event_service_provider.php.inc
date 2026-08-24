<?php

namespace App\Fixtures\Laravel11\UpdateEmailVerificationSetupRector;

class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateEmailVerificationSetupRector;

// @laravel-upgrade email-verification-setup EventServiceProvider now auto-registers SendEmailVerificationNotification. Define configureEmailVerification() if you want to opt out of the automatic listener registration.
class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
}

?>
