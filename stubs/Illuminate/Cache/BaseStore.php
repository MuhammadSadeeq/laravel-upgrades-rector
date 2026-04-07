<?php

namespace Illuminate\Cache;

abstract class BaseStore
{
    public function touch(string $key, int $seconds): bool
    {
        return true;
    }
}
