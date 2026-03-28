<?php

namespace Laravel\Cashier;

trait Billable
{
    public function hasPaymentMethod(?string $type = null): bool { return false; }

    /** @return array<int, mixed> */
    public function paymentMethods(?string $type = null): array { return []; }

    public function deletePaymentMethods(?string $type = null): void {}
}
