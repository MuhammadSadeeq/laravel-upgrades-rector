<?php

namespace App;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;

class ConfiguredEvents extends EventServiceProvider
{
    protected function configureEmailVerification(): void {}
}
