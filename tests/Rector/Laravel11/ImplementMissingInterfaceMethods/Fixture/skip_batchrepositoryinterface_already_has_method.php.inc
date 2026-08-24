<?php

namespace App\Bus;

use Illuminate\Bus\BatchRepository;

class CustomBatchRepository implements BatchRepository
{
    public function rollBack(): void
    {
        // TODO: Laravel 11 — implement rollBack() to satisfy the updated contract.
    }
}

?>
