<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Psr\Log\LoggerInterface;

class DataProcessor
{
    public function __construct(
        private ?Connection $connection = null,
        private ?LoggerInterface $logger = null,
        private string $name = 'default'
    ) {
    }
}

?>
-----
<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Psr\Log\LoggerInterface;

class DataProcessor
{
    /** Laravel 12: Container now respects default values for constructor parameters. Class-typed parameters with default values will use the default instead of being resolved from the container. */
    public function __construct(
        private ?Connection $connection = null,
        private ?LoggerInterface $logger = null,
        private string $name = 'default'
    ) {
    }
}

?>
