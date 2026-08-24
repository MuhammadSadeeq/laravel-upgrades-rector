<?php // @loads

namespace App\Queue;

use Illuminate\Contracts\Queue\Queue;

class CustomQueue implements Queue
{
    public function size($queue = null)
    {
    }

    public function push($job, $data = '', $queue = null)
    {
    }

    public function pushOn($queue, $job, $data = '')
    {
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
    }

    public function laterOn($queue, $delay, $job, $data = '')
    {
    }

    public function bulk($jobs, $data = '', $queue = null)
    {
    }

    public function pop($queue = null)
    {
    }

    public function getConnectionName()
    {
    }

    public function setConnectionName($name)
    {
    }
}

?>
-----
<?php // @loads

namespace App\Queue;

use Illuminate\Contracts\Queue\Queue;

class CustomQueue implements Queue
{
    public function size($queue = null)
    {
    }

    public function push($job, $data = '', $queue = null)
    {
    }

    public function pushOn($queue, $job, $data = '')
    {
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
    }

    public function laterOn($queue, $delay, $job, $data = '')
    {
    }

    public function bulk($jobs, $data = '', $queue = null)
    {
    }

    public function pop($queue = null)
    {
    }

    public function getConnectionName()
    {
    }

    public function setConnectionName($name)
    {
    }
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
