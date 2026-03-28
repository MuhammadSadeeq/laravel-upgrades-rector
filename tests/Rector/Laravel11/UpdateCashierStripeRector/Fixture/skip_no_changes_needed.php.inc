<?php

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
