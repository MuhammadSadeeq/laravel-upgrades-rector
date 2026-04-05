<?php

namespace Illuminate\Support;

class Manager
{
    public function extend(string $driver, \Closure $callback): static
    {
        return $this;
    }
}
