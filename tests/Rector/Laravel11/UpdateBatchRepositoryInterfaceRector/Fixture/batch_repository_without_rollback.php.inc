<?php

use Illuminate\Bus\BatchRepository;

class CustomBatchRepository implements BatchRepository
{
    public function get($batchId)
    {
        // implementation
    }

    public function store($batch)
    {
        // implementation
    }

    public function incrementTotalJobs($batchId, $amount)
    {
        // implementation
    }

    public function decrementPendingJobs($batchId, $failedJobIds)
    {
        // implementation
    }

    public function updateBatch($batch)
    {
        // implementation
    }
}

?>
-----
<?php

use Illuminate\Bus\BatchRepository;

/** Laravel 11: BatchRepository interface requires new rollBack method. Add: public function rollBack(); */
class CustomBatchRepository implements BatchRepository
{
    public function get($batchId)
    {
        // implementation
    }

    public function store($batch)
    {
        // implementation
    }

    public function incrementTotalJobs($batchId, $amount)
    {
        // implementation
    }

    public function decrementPendingJobs($batchId, $failedJobIds)
    {
        // implementation
    }

    public function updateBatch($batch)
    {
        // implementation
    }
}

?>