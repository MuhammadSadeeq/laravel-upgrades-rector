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
-----
<?php

namespace App\Http;

use Illuminate\Contracts\Routing\ResponseFactory;

class CustomResponseFactory implements ResponseFactory
{
    public function eventStream(\Closure $callback, array $headers = [], \Illuminate\Http\StreamedEvent|string|null $endStreamWith = '</stream>'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // custom implementation
    }
}

?>
