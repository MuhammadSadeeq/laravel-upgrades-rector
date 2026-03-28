<?php

namespace Illuminate\Cache\RateLimiting;

class Limit
{
    public function __construct(string $key = '', int $maxAttempts = 60, int $decaySeconds = 60)
    {
    }
}
