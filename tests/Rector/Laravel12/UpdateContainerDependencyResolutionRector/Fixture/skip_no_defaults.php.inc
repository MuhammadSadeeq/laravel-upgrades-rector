<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Psr\Log\LoggerInterface;

class RequiredDependencies
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {
    }
}

?>
