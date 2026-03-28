<?php

class InvoiceHandler
{
    public function handleInvoice($invoice)
    {
        $result = $invoice->charge();
    }
}

?>
