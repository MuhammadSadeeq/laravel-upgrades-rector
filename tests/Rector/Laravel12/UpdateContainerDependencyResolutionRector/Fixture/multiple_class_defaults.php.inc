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
    // Laravel 12: Container now respects default parameter values when resolving dependencies. This constructor may behave differently.
    public function __construct(private ?Connection $connection = null, private ?LoggerInterface $logger = null, private string $name = 'default')
    {
    }
}

?>
