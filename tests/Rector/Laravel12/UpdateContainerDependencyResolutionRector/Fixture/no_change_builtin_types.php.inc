<?php

namespace App\Services;

class SimpleService
{
    public function __construct(
        private string $name = 'default',
        private int $timeout = 30,
        private bool $enabled = true
    ) {
    }
}

?>
