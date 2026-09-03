<?php // @loads

namespace App\Jobs\Middleware;

use Illuminate\Queue\Middleware\ThrottlesExceptions;

final class RetryUntyped extends ThrottlesExceptions
{
    protected $decayMinutes = 5;

    public function middleware(): array
    {
        return [[$this->decayMinutes]];
    }
}

?>
-----
<?php // @loads

namespace App\Jobs\Middleware;

use Illuminate\Queue\Middleware\ThrottlesExceptions;

final class RetryUntyped extends ThrottlesExceptions
{
    protected $decaySeconds = 5 * 60;

    public function middleware(): array
    {
        return [[$this->decaySeconds]];
    }
}

?>
