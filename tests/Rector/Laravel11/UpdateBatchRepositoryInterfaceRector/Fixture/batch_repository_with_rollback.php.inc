<?php

use Illuminate\Bus\BatchRepository;

class CompleteBatchRepository implements BatchRepository
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

    public function rollBack()
    {
        // implementation
    }
}

?>