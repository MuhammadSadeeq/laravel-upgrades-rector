<?php // @loads

namespace App\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;

class CustomRepository implements Repository
{
    public function get($key, $default = null): mixed
    {
    }

    public function set($key, $value, $ttl = null): bool
    {
    }

    public function delete($key): bool
    {
    }

    public function clear(): bool
    {
    }

    public function getMultiple($keys, $default = null): iterable
    {
    }

    public function setMultiple($values, $ttl = null): bool
    {
    }

    public function deleteMultiple($keys): bool
    {
    }

    public function has($key): bool
    {
    }

    public function pull($key, $default = null)
    {
    }

    public function put($key, $value, $ttl = null)
    {
    }

    public function add($key, $value, $ttl = null)
    {
    }

    public function increment($key, $value = 1)
    {
    }

    public function decrement($key, $value = 1)
    {
    }

    public function forever($key, $value)
    {
    }

    public function remember($key, $ttl, Closure $callback)
    {
    }

    public function sear($key, Closure $callback)
    {
    }

    public function rememberForever($key, Closure $callback)
    {
    }

    public function forget($key)
    {
    }

    public function getStore()
    {
    }
}

?>
-----
<?php // @loads

namespace App\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;

class CustomRepository implements Repository
{
    public function get($key, $default = null): mixed
    {
    }

    public function set($key, $value, $ttl = null): bool
    {
    }

    public function delete($key): bool
    {
    }

    public function clear(): bool
    {
    }

    public function getMultiple($keys, $default = null): iterable
    {
    }

    public function setMultiple($values, $ttl = null): bool
    {
    }

    public function deleteMultiple($keys): bool
    {
    }

    public function has($key): bool
    {
    }

    public function pull($key, $default = null)
    {
    }

    public function put($key, $value, $ttl = null)
    {
    }

    public function add($key, $value, $ttl = null)
    {
    }

    public function increment($key, $value = 1)
    {
    }

    public function decrement($key, $value = 1)
    {
    }

    public function forever($key, $value)
    {
    }

    public function remember($key, $ttl, Closure $callback)
    {
    }

    public function sear($key, Closure $callback)
    {
    }

    public function rememberForever($key, Closure $callback)
    {
    }

    public function forget($key)
    {
    }

    public function getStore()
    {
    }
    public function touch($key, $ttl)
    {
        // TODO: Laravel 13 — implement touch() to satisfy the updated contract.
    }
}

?>
