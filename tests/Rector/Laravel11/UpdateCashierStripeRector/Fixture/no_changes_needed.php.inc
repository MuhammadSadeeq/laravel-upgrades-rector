<?php

class RegularClass
{
    public function regularMethod()
    {
        $user = new User();

        // These should not be changed
        $user->someMethod();
        $user->anotherMethod('parameter');
        $result = $user->getValue();
    }

    public function newSubscriptionType()
    {
        // This should not be changed as it's already the new name
        return 'type';
    }
}

?>