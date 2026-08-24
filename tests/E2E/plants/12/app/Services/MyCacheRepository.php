<?php

namespace App\Services;

use Closure;
use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\Repository;
use Psr\SimpleCache\CacheInterface;

class MyCacheRepository implements CacheInterface, Repository
{
    public function get($key, $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        return true;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return [];
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function pull($key, $default = null)
    {
        return $default;
    }

    public function put($key, $value, $ttl = null)
    {
        return true;
    }

    public function add($key, $value, $ttl = null)
    {
        return true;
    }

    public function increment($key, $value = 1)
    {
        return 1;
    }

    public function decrement($key, $value = 1)
    {
        return 1;
    }

    public function forever($key, $value)
    {
        return true;
    }

    public function remember($key, $ttl, Closure $callback)
    {
        return $callback();
    }

    public function sear($key, Closure $callback)
    {
        return $callback();
    }

    public function rememberForever($key, Closure $callback)
    {
        return $callback();
    }

    public function forget($key)
    {
        return true;
    }

    public function getStore()
    {
        return new ArrayStore;
    }
}
