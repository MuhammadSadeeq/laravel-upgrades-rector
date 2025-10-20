<?php

class NonSchemaClass
{
    public function testMethods()
    {
        // Should not change - not Schema class
        $other = SomeClass::getTables();
        $another = MyClass::getViews();

        // Should not change - instance methods
        $instance = new DatabaseHelper();
        $tables = $instance->getTables();
        $views = $instance->getViews();

        return [$other, $another, $tables, $views];
    }
}

?>
-----
<?php

class NonSchemaClass
{
    public function testMethods()
    {
        // Should not change - not Schema class
        $other = SomeClass::getTables();
        $another = MyClass::getViews();

        // Should not change - instance methods
        $instance = new DatabaseHelper();
        $tables = $instance->getTables();
        $views = $instance->getViews();

        return [$other, $another, $tables, $views];
    }
}

?>