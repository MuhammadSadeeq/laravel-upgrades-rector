<?php

use Illuminate\Bus\BatchRepository;
use SomeOther\Interface as SomeInterface;

class MultipleBatchRepository implements SomeInterface, BatchRepository
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

    public function someInterfaceMethod()
    {
        // implementation for other interface
    }
}

?>
-----
<?php

use Illuminate\Bus\BatchRepository;
use SomeOther\Interface as SomeInterface;

/** Laravel 11: BatchRepository interface requires new rollBack method. Add: public function rollBack(); */
class MultipleBatchRepository implements SomeInterface, BatchRepository
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

    public function someInterfaceMethod()
    {
        // implementation for other interface
    }
}

?>