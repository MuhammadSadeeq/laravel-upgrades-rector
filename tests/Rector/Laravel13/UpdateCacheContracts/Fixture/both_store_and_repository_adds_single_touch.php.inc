<?php // @loads

namespace App\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;

class DualCache implements Store, Repository
{
    public function get($key, $default = null): mixed
    {
        return null;
    }

    public function set($key, $value, $ttl = null): bool
    {
        return true;
    }

    public function delete($key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        return [];
    }

    public function setMultiple($values, $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple($keys): bool
    {
        return true;
    }

    public function has($key): bool
    {
        return true;
    }

    public function many(array $keys)
    {
        return [];
    }

    public function put($key, $value, $ttl = null)
    {
        return true;
    }

    public function putMany(array $values, $seconds)
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

    public function forget($key)
    {
        return true;
    }

    public function flush()
    {
        return true;
    }

    public function getPrefix()
    {
        return '';
    }

    public function pull($key, $default = null)
    {
        return null;
    }

    public function add($key, $value, $ttl = null)
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

    public function getStore()
    {
        return new \Illuminate\Cache\ArrayStore();
    }
}

?>
-----
<?php // @loads

namespace App\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;

class DualCache implements Store, Repository
{
    public function get($key, $default = null): mixed
    {
        return null;
    }

    public function set($key, $value, $ttl = null): bool
    {
        return true;
    }

    public function delete($key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        return [];
    }

    public function setMultiple($values, $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple($keys): bool
    {
        return true;
    }

    public function has($key): bool
    {
        return true;
    }

    public function many(array $keys)
    {
        return [];
    }

    public function put($key, $value, $ttl = null)
    {
        return true;
    }

    public function putMany(array $values, $seconds)
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

    public function forget($key)
    {
        return true;
    }

    public function flush()
    {
        return true;
    }

    public function getPrefix()
    {
        return '';
    }

    public function pull($key, $default = null)
    {
        return null;
    }

    public function add($key, $value, $ttl = null)
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

    public function getStore()
    {
        return new \Illuminate\Cache\ArrayStore();
    }
    public function touch($key, $seconds)
    {
        // TODO: Laravel 13 — implement touch() to satisfy the updated contract.
    }
}

?>
