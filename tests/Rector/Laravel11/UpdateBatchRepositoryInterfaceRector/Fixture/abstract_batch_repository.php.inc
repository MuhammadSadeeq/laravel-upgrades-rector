<?php

use Illuminate\Bus\BatchRepository;

abstract class AbstractBatchRepository implements BatchRepository
{
    public function get($batchId)
    {
        // base implementation
    }

    public function store($batch)
    {
        // base implementation
    }

    abstract public function incrementTotalJobs($batchId, $amount);

    abstract public function decrementPendingJobs($batchId, $failedJobIds);

    abstract public function updateBatch($batch);
}

?>
-----
<?php

use Illuminate\Bus\BatchRepository;

/** Laravel 11: BatchRepository interface requires new rollBack method. Add: public function rollBack(); */
abstract class AbstractBatchRepository implements BatchRepository
{
    public function get($batchId)
    {
        // base implementation
    }

    public function store($batch)
    {
        // base implementation
    }

    abstract public function incrementTotalJobs($batchId, $amount);

    abstract public function decrementPendingJobs($batchId, $failedJobIds);

    abstract public function updateBatch($batch);
}

?>