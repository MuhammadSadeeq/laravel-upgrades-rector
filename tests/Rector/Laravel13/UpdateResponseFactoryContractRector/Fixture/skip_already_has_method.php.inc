<?php

namespace App\Http;

use Illuminate\Contracts\Routing\ResponseFactory;

class CustomResponseFactory implements ResponseFactory
{
    public function eventStream(callable $callback, ?string $endStream = null, array $headers = [])
    {
        // custom implementation
    }
}

?>
