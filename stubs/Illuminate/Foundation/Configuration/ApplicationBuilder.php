<?php

namespace Illuminate\Foundation\Configuration;

class ApplicationBuilder
{
    public function withScheduling(callable $callback): static
    {
        return $this;
    }
}
