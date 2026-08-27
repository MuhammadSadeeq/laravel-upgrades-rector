<?php

namespace App;

use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;

class ListenerEvents extends EventServiceProvider
{
    protected $listen = [
        Registered::class => [],
    ];
}
