<?php

namespace App\Billing;

/** A domain quota, unrelated to ThrottlesExceptions. */
final class Quota
{
    protected int $decayMinutes = 5;

    public function decay(): int
    {
        return $this->decayMinutes;
    }
}
