<?php

namespace App;

use Illuminate\Support\ServiceProvider;

class OtherConfigProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([__DIR__.'/stubs/services.php' => config_path('services.php')]);
    }
}
