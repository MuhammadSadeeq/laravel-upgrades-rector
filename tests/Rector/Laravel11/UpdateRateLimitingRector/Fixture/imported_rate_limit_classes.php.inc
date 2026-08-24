<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

use Illuminate\Cache\RateLimiting\GlobalLimit;
use Illuminate\Cache\RateLimiting\Limit;

class LegacyRateLimiterPatterns
{
    public function configure(): array
    {
        return [
            new GlobalLimit(100, 2),
            new Limit('api', 100, 10),
        ];
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

use Illuminate\Cache\RateLimiting\GlobalLimit;
use Illuminate\Cache\RateLimiting\Limit;

class LegacyRateLimiterPatterns
{
    public function configure(): array
    {
        return [
            new GlobalLimit(100, 2 * 60),
            new Limit('api', 100, 10 * 60),
        ];
    }
}

?>
