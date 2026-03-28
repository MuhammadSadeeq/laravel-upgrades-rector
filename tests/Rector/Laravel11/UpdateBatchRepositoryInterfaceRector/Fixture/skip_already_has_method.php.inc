<?php

namespace App\Bus;

use Illuminate\Bus\BatchRepository;

class CustomBatchRepository implements BatchRepository
{
    public function rollBack(): void
    {
    }
}

?>
