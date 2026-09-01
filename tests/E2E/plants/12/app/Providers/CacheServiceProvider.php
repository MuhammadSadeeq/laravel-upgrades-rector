<?php

namespace App\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

/** This provider is not registered; its extend callback is upgrade-only input. */
final class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Cache::extend('e2e', static function ($app, array $config): mixed {
            return $app['cache.store'];
        });
    }
}
