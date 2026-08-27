<?php

namespace App;

use Illuminate\Support\ServiceProvider;

class ConfigProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([__DIR__.'/stubs/app.php' => config_path('app.php')]);
    }
}
