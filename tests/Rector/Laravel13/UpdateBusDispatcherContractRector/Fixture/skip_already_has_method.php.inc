<?php

namespace App\Bus;

use Illuminate\Contracts\Bus\Dispatcher;

class CustomDispatcher implements Dispatcher
{
    public function dispatchAfterResponse($command, $handler = null): mixed
    {
        return $this->dispatch($command);
    }
}

?>
-----
<?php

namespace App\Bus;

use Illuminate\Contracts\Bus\Dispatcher;

class CustomDispatcher implements Dispatcher
{
    public function dispatchAfterResponse(mixed $command, mixed $handler = null): void
    {
        $this->dispatch($command);
    }
    public function chain(\Illuminate\Support\Collection|array|null $jobs = null): mixed
    {
        // TODO: Implement chain() method.
    }
}

?>
