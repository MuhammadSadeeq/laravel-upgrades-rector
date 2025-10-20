<?php

class PaymentProcessor
{
    public function processCancel($billable)
    {
        // Variable named 'billable' should be detected
        $billable->cancel();
        $billable->cancelNow();
        $billable->cancelAt(now()->addWeek());
    }
}

?>
-----
<?php

class PaymentProcessor
{
    public function processCancel($billable)
    {
        // Variable named 'billable' should be detected
        $billable->cancel();
        $billable->cancelNow();
        $billable->cancelAt(now()->addWeek());
    }
}

?>