<?php

namespace Illuminate\Contracts\Routing;

interface ResponseFactory
{
    public function make(mixed $content = '', int $status = 200, array $headers = []): mixed;

    public function noContent(int $status = 204, array $headers = []): mixed;

    public function view(string $view, array $data = [], int $status = 200, array $headers = []): mixed;

    public function json(mixed $data = [], int $status = 200, array $headers = [], int $options = 0): mixed;

    public function jsonp(string $callback, mixed $data = [], int $status = 200, array $headers = [], int $options = 0): mixed;

    public function stream(callable $callback, int $status = 200, array $headers = []): mixed;

    public function streamDownload(callable $callback, ?string $name = null, array $headers = [], ?string $disposition = 'attachment'): mixed;

    public function download(mixed $file, ?string $name = null, array $headers = [], ?string $disposition = 'attachment'): mixed;

    public function redirectTo(string $path, int $status = 302, array $headers = [], ?bool $secure = null): mixed;

    public function redirectToRoute(string $route, mixed $parameters = [], int $status = 302, array $headers = []): mixed;

    public function redirectToAction(array|string $action, mixed $parameters = [], int $status = 302, array $headers = []): mixed;

    public function redirectGuest(string $path, int $status = 302, array $headers = [], ?bool $secure = null): mixed;

    public function redirectToIntended(string $default = '/', int $status = 302, array $headers = [], ?bool $secure = null): mixed;

    public function eventStream(callable $callback, ?string $endStream = null, array $headers = []): mixed;
}
