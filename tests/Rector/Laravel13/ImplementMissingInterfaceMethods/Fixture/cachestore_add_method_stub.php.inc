<?php // @loads

namespace App\Cache;

use Illuminate\Contracts\Cache\Store;

class CustomStore implements Store
{
    public function get($key)
    {
    }

    public function many(array $keys)
    {
    }

    public function put($key, $value, $seconds)
    {
    }

    public function putMany(array $values, $seconds)
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

    public function forget($key)
    {
    }

    public function flush()
    {
    }

    public function getPrefix()
    {
    }
}

?>
-----
<?php // @loads

namespace App\Cache;

use Illuminate\Contracts\Cache\Store;

class CustomStore implements Store
{
    public function get($key)
    {
    }

    public function many(array $keys)
    {
    }

    public function put($key, $value, $seconds)
    {
    }

    public function putMany(array $values, $seconds)
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

    public function forget($key)
    {
    }

    public function flush()
    {
    }

    public function getPrefix()
    {
    }
    public function touch($key, $seconds)
    {
        // TODO: Laravel 13 — implement touch() to satisfy the updated contract.
    }
}

?>
