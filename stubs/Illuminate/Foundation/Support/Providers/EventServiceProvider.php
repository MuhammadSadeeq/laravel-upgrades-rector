<?php

namespace Illuminate\Foundation\Support\Providers;

use Illuminate\Support\ServiceProvider;

abstract class EventServiceProvider extends ServiceProvider
{
    /** @var array<string, array<int, string>> */
    protected $listen = [];
}
