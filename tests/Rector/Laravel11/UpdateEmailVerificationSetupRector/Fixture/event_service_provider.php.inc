<?php

class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
}

?>
-----
<?php

// @laravel-upgrade email-verification-setup EventServiceProvider now auto-registers SendEmailVerificationNotification. Define configureEmailVerification() if you want to opt out of the automatic listener registration.
class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
}

?>
