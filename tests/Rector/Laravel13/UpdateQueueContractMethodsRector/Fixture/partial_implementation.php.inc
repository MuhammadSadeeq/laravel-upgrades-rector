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
    public function reservedSize(?string $queue = null): int
    {
        return 0;
    }
    public function creationTimeOfOldestPendingJob(?string $queue = null): ?int
    {
        return null;
    }
}

?>
