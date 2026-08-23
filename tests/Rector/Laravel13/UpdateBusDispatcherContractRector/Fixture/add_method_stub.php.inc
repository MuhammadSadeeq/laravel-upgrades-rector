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
    public function dispatchAfterResponse($command, $handler = null)
    {
        // TODO: Laravel 13 — implement dispatchAfterResponse() to satisfy the updated contract.
    }
}

?>
