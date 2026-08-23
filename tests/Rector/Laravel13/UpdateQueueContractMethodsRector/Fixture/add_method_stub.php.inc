<?php

namespace App\Queue;

use Illuminate\Contracts\Queue\Queue;

class CustomQueue implements Queue
{
}

?>
-----
<?php

namespace App\Queue;

use Illuminate\Contracts\Queue\Queue;

class CustomQueue implements Queue
{
    public function pendingSize($queue = null): int
    {
        // TODO: Laravel 13 — implement pendingSize() to satisfy the updated contract.
        return 0;
    }
    public function delayedSize($queue = null): int
    {
        // TODO: Laravel 13 — implement delayedSize() to satisfy the updated contract.
        return 0;
    }
    public function reservedSize($queue = null): int
    {
        // TODO: Laravel 13 — implement reservedSize() to satisfy the updated contract.
        return 0;
    }
    public function creationTimeOfOldestPendingJob($queue = null): ?int
    {
        // TODO: Laravel 13 — implement creationTimeOfOldestPendingJob() to satisfy the updated contract.
        return null;
    }
}

?>
