<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeRector;

class RegularClass
{
    public function regularMethod()
    {
        $user = new \stdClass();

        $user->someMethod();
        $user->anotherMethod('parameter');
        $result = $user->getValue();
    }
}

?>
