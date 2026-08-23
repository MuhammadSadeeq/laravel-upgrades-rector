<?php

namespace App\Services;

use Illuminate\Database\Connection;

class AlreadyCommented
{
    // Laravel 12: Container now respects default parameter values when resolving dependencies. This constructor may behave differently.
    public function __construct(
        private ?Connection $connection = null
    ) {
    }
}

?>
