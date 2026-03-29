<?php

namespace Illuminate\Http\Client;

class Response
{
    /**
     * @param callable|null $callback
     * @return $this
     */
    public function throw($callback = null): self
    {
        return $this;
    }

    /**
     * @param bool $condition
     * @param callable|null $callback
     * @return $this
     */
    public function throwIf($condition, $callback = null): self
    {
        return $this;
    }
}
