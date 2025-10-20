<?php

class SomeOtherClass
{
    public function test()
    {
        // Should not change - different class names
        $other1 = new TokenRepository($conn, $hash, 'table', 'key', 60);
        $other2 = new DatabaseRepository($conn, $hash, 'table', 'key', 90);
        $other3 = new SomeClass($conn, $hash, 'table', 'key', 120);

        return [$other1, $other2, $other3];
    }
}

?>
-----
<?php

class SomeOtherClass
{
    public function test()
    {
        // Should not change - different class names
        $other1 = new TokenRepository($conn, $hash, 'table', 'key', 60);
        $other2 = new DatabaseRepository($conn, $hash, 'table', 'key', 90);
        $other3 = new SomeClass($conn, $hash, 'table', 'key', 120);

        return [$other1, $other2, $other3];
    }
}

?>