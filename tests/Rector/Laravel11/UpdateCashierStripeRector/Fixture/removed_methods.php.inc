<?php

class InvoiceHandler
{
    public function handleInvoice($invoice)
    {
        if ($invoice->isDeleted()) {
            // Handle deleted invoice
        }
    }
}

?>
-----
<?php

class InvoiceHandler
{
    public function handleInvoice($invoice)
    {
        if ($invoice->isDeleted()) {
            // Handle deleted invoice
        }
    }
}

?>