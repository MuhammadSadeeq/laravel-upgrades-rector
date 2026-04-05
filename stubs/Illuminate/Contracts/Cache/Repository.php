<?php

namespace Illuminate\Contracts\Cache;

interface Repository
{
    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value, mixed $ttl = null): bool;

    public function touch(string $key, int $seconds): bool;
}
