<?php

use Illuminate\Auth\Events\Registered;

class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
    protected $listen = [
        Registered::class => [
            'App\Listeners\SendEmailVerificationNotification',
        ],
    ];
}

?>
