<?php

namespace App\Jobs\Middleware;

use Illuminate\Queue\Middleware\ThrottlesExceptions;

final class RetryWithBackoff extends ThrottlesExceptions
{
    protected int $decayMinutes = 5;

    public function middleware(): array
    {
        return [[$this->decayMinutes]];
    }
}

?>
-----
<?php

namespace App\Jobs\Middleware;

use Illuminate\Queue\Middleware\ThrottlesExceptions;

final class RetryWithBackoff extends ThrottlesExceptions
{
    protected int $decaySeconds = 5 * 60;

    public function middleware(): array
    {
        return [[$this->decaySeconds]];
    }
}

?>
