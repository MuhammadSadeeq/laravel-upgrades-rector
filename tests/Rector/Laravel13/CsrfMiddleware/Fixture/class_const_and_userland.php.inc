<?php

namespace App\Providers;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;

final class AppServiceProvider
{
    public function boot(Application $app): void
    {
        Route::middleware(ValidateCsrfToken::class)
            ->group(fn () => null);

        // A userland class that only shares the short name is never renamed
        $csrf = new \App\Http\Csrf\VerifyCsrfToken();
        echo $csrf->name();
    }
}

?>
-----
<?php

namespace App\Providers;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use App\Http\Csrf\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;

final class AppServiceProvider
{
    public function boot(Application $app): void
    {
        Route::middleware(PreventRequestForgery::class)
            ->group(fn () => null);

        // A userland class that only shares the short name is never renamed
        $csrf = new VerifyCsrfToken();
        echo $csrf->name();
    }
}

?>
