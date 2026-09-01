<?php

namespace App\Billing;

/** An application limit, unrelated to Illuminate\Cache\RateLimiting\Limit. */
final class Limit
{
    public function window(): int
    {
        return 5 * 60;
    }
}
