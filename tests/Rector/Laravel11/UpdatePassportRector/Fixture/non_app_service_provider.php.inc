<?php

use Illuminate\Support\ServiceProvider;

class OtherServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // This should not be changed as it's not AppServiceProvider
    }

    public function register(): void
    {
        // Register code
    }
}

?>