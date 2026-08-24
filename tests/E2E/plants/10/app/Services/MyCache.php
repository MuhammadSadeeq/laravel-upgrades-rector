<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Store;

class MyCache implements Store
{
    public function get($key)
    {
        return null;
    }

    public function many(array $keys)
    {
        return [];
    }

    public function put($key, $value, $seconds)
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
}
