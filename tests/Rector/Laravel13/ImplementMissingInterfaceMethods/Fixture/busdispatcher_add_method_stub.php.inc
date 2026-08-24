<?php // @loads

namespace App\Bus;

use Illuminate\Contracts\Bus\Dispatcher;

class CustomDispatcher implements Dispatcher
{
    public function dispatch($command)
    {
    }

    public function dispatchSync($command, $handler = null)
    {
    }

    public function dispatchNow($command, $handler = null)
    {
    }

    public function chain($jobs = null)
    {
    }

    public function hasCommandHandler($command)
    {
    }

    public function getCommandHandler($command)
    {
    }

    public function pipeThrough(array $pipes)
    {
    }

    public function map(array $map)
    {
    }
}

?>
-----
<?php // @loads

namespace App\Bus;

use Illuminate\Contracts\Bus\Dispatcher;

class CustomDispatcher implements Dispatcher
{
    public function dispatch($command)
    {
    }

    public function dispatchSync($command, $handler = null)
    {
    }

    public function dispatchNow($command, $handler = null)
    {
    }

    public function chain($jobs = null)
    {
    }

    public function hasCommandHandler($command)
    {
    }

    public function getCommandHandler($command)
    {
    }

    public function pipeThrough(array $pipes)
    {
    }

    public function map(array $map)
    {
    }
    public function dispatchAfterResponse($command, $handler = null)
    {
        // TODO: Laravel 13 — implement dispatchAfterResponse() to satisfy the updated contract.
    }
}

?>
