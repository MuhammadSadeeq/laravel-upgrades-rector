<?php

namespace Illuminate\Queue\Middleware;

class ThrottlesExceptions
{
    public int $decaySeconds = 600;

    public function __construct(int $maxAttempts = 10, int $decaySeconds = 600)
    {
    }
}
