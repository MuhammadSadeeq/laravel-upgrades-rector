<?php

namespace App\Services;

use Illuminate\Contracts\Queue\Queue;

class MyQueue implements Queue
{
    public function size($queue = null)
    {
        return 0;
    }

    public function push($job, $data = '', $queue = null)
    {
        return true;
    }

    public function pushOn($queue, $job, $data = '')
    {
        return true;
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return true;
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return true;
    }

    public function laterOn($queue, $delay, $job, $data = '')
    {
        return true;
    }

    public function bulk($jobs, $data = '', $queue = null)
    {
        return true;
    }

    public function pop($queue = null)
    {
        return true;
    }

    public function getConnectionName()
    {
        return 'sync';
    }

    public function setConnectionName($name)
    {
        return $this;
    }
}
