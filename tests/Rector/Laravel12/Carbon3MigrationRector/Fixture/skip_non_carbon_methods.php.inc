<?php

class DynamicMethodTest
{
    public function testDynamicCalls()
    {
        $obj = new SomeClass();
        $method = 'diffInSeconds';

        // Dynamic method names are skipped (getName returns null)
        $result = $obj->$method($other);

        return $result;
    }
}

?>
