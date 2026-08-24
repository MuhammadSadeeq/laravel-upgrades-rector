<?php // @loads

namespace App\Http;

use Illuminate\Contracts\Routing\ResponseFactory;

class CustomResponseFactory implements ResponseFactory
{
    public function make($content = '', $status = 200, array $headers = [])
    {
    }

    public function noContent($status = 204, array $headers = [])
    {
    }

    public function view($view, $data = [], $status = 200, array $headers = [])
    {
    }

    public function json($data = [], $status = 200, array $headers = [], $options = 0)
    {
    }

    public function jsonp($callback, $data = [], $status = 200, array $headers = [], $options = 0)
    {
    }

    public function stream($callback, $status = 200, array $headers = [])
    {
    }

    public function streamJson($data, $status = 200, $headers = [], $encodingOptions = 15)
    {
    }

    public function streamDownload($callback, $name = null, array $headers = [], $disposition = 'attachment')
    {
    }

    public function download($file, $name = null, array $headers = [], $disposition = 'attachment')
    {
    }

    public function file($file, array $headers = [])
    {
    }

    public function redirectTo($path, $status = 302, $headers = [], $secure = null)
    {
    }

    public function redirectToRoute($route, $parameters = [], $status = 302, $headers = [])
    {
    }

    public function redirectToAction($action, $parameters = [], $status = 302, $headers = [])
    {
    }

    public function redirectGuest($path, $status = 302, $headers = [], $secure = null)
    {
    }

    public function redirectToIntended($default = '/', $status = 302, $headers = [], $secure = null)
    {
    }
}

?>
-----
<?php // @loads

namespace App\Http;

use Illuminate\Contracts\Routing\ResponseFactory;

class CustomResponseFactory implements ResponseFactory
{
    public function make($content = '', $status = 200, array $headers = [])
    {
    }

    public function noContent($status = 204, array $headers = [])
    {
    }

    public function view($view, $data = [], $status = 200, array $headers = [])
    {
    }

    public function json($data = [], $status = 200, array $headers = [], $options = 0)
    {
    }

    public function jsonp($callback, $data = [], $status = 200, array $headers = [], $options = 0)
    {
    }

    public function stream($callback, $status = 200, array $headers = [])
    {
    }

    public function streamJson($data, $status = 200, $headers = [], $encodingOptions = 15)
    {
    }

    public function streamDownload($callback, $name = null, array $headers = [], $disposition = 'attachment')
    {
    }

    public function download($file, $name = null, array $headers = [], $disposition = 'attachment')
    {
    }

    public function file($file, array $headers = [])
    {
    }

    public function redirectTo($path, $status = 302, $headers = [], $secure = null)
    {
    }

    public function redirectToRoute($route, $parameters = [], $status = 302, $headers = [])
    {
    }

    public function redirectToAction($action, $parameters = [], $status = 302, $headers = [])
    {
    }

    public function redirectGuest($path, $status = 302, $headers = [], $secure = null)
    {
    }

    public function redirectToIntended($default = '/', $status = 302, $headers = [], $secure = null)
    {
    }
    public function eventStream(\Closure $callback, array $headers = [], \Illuminate\Http\StreamedEvent|string|null $endStreamWith = '</stream>'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // TODO: Laravel 13 — implement eventStream() to satisfy the updated contract.
    }
}

?>
