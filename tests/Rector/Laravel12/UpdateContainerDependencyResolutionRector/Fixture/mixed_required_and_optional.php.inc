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
    /** Laravel 12: Container now respects default values for constructor parameters. Class-typed parameters with default values will use the default instead of being resolved from the container. */
    public function __construct(
        private CacheRepository $cache,
        private ?LoggerInterface $logger = null
    ) {
    }
}

?>
