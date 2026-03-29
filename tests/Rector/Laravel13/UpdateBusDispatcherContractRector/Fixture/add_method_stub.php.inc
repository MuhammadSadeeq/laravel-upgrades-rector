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
    public function dispatchAfterResponse($command, $handler = null): mixed
    {
        return null;
    }
}

?>
