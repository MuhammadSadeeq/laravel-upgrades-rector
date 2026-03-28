<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Log\LoggerInterface;

class CacheService
{
    public function __construct(
        private CacheRepository $cache,
        private ?LoggerInterface $logger = null
    ) {
    }
}

?>
-----
<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Log\LoggerInterface;

class CacheService
{
    // Laravel 12: Container now respects default parameter values when resolving dependencies. This constructor may behave differently.
    public function __construct(private CacheRepository $cache, private ?LoggerInterface $logger = null)
    {
    }
}

?>
