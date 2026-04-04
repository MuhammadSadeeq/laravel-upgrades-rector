<?php

class PackageServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/stubs/app.php' => config_path('app.php'),
        ]);
    }
}

?>
-----
<?php

class PackageServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        // Laravel 11: publish service providers via ServiceProvider::addProviderToBootstrapFile() instead of modifying config/app.php directly.
        $this->publishes([
            __DIR__ . '/stubs/app.php' => config_path('app.php'),
        ]);
    }
}

?>
