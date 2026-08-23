<?php

namespace App\Queue;

use Illuminate\Contracts\Queue\Queue;

class CustomQueue implements Queue
{
    public function pendingSize(?string $queue = null): int
    {
        return $this->connection->llen($queue);
    }

    public function delayedSize(?string $queue = null): int
    {
        return $this->connection->zcard($queue);
    }
}

?>
-----
<?php

namespace App\Queue;

use Illuminate\Contracts\Queue\Queue;

class CustomQueue implements Queue
{
    public function pendingSize(?string $queue = null): int
    {
        return $this->connection->llen($queue);
    }

    public function delayedSize(?string $queue = null): int
    {
        return $this->connection->zcard($queue);
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
