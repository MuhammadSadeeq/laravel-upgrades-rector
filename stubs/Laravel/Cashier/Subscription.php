<?php

namespace Laravel\Cashier;

class Subscription
{
    public function cancel(): self
    {
        return $this;
    }

    public function cancelNow(): self
    {
        return $this;
    }

    public function cancelAt(\DateTimeInterface $endsAt): self
    {
        return $this;
    }
}
