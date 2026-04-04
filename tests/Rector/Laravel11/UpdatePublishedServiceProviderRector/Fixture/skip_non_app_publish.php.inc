<?php

class PackageServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('package.php'),
        ]);
    }
}

?>
