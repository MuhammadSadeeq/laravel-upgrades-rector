<?php

namespace App\Bus;

use Illuminate\Contracts\Bus\Dispatcher;

class CustomDispatcher implements Dispatcher
{
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
        // TODO: Implement dispatchAfterResponse() method.
    }
    public function chain(\Illuminate\Support\Collection|array|null $jobs = null): mixed
    {
        // TODO: Implement chain() method.
    }
}

?>
