<?php

namespace App;

use Illuminate\Support\ServiceProvider;

class LiteralConfigProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([__DIR__.'/stubs/app.php' => 'config/app.php']);
    }
}
