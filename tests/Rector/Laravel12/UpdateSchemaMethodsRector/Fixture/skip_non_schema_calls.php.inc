<?php

class NonSchemaClass
{
    public function testMethods()
    {
        $other = SomeClass::getTables();
        $another = MyClass::getViews();
    }
}

?>
