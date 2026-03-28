<?php

namespace Illuminate\Cache\RateLimiting;

class GlobalLimit
{
    public function __construct(int $maxAttempts, int $decaySeconds = 60)
    {
    }
}
