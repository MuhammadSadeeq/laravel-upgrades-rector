<?php

namespace App\Fixtures\Laravel12\UpdateSchemaMethodsRector;

class NonSchemaClass
{
    public function testMethods()
    {
        $other = SomeClass::getTables();
        $another = MyClass::getViews();
    }
}

?>
