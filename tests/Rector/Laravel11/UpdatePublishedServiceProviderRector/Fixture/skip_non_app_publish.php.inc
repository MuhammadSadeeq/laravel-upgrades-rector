<?php

namespace App\Fixtures\Laravel11\UpdatePublishedServiceProviderRector;

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
