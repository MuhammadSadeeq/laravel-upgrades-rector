<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeRector;

class InvoiceHandler
{
    public function handleInvoice($invoice)
    {
        $result = $invoice->charge();
    }
}

?>
