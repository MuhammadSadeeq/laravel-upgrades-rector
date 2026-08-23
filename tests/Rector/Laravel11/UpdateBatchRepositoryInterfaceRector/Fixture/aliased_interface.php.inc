<?php

use Illuminate\Bus\BatchRepository as BatchRepositoryContract;

class LegacyBatchRepository implements BatchRepositoryContract
{
}

?>
-----
<?php

use Illuminate\Bus\BatchRepository as BatchRepositoryContract;

class LegacyBatchRepository implements BatchRepositoryContract
{
    public function rollBack(): void
    {
        // TODO: Laravel 11 — implement rollBack() to satisfy the updated contract.
    }
}

?>
