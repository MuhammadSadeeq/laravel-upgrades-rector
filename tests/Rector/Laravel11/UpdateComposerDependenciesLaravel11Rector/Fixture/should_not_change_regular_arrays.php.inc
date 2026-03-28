<?php

class RegularArrayTest
{
    public function getData()
    {
        // This should NOT be changed - it's not a composer dependency array
        return [
            'laravel/framework' => 'Some random value',
            'name' => 'John Doe',
        ];
    }

    public function getMapping()
    {
        // Single package-like key - should NOT be changed (needs 2+ to be composer-like)
        return [
            'some/package' => 'value',
            'regular_key' => 'value2',
        ];
    }

    public function getDescriptions()
    {
        // Array with package names as keys but clearly not a composer array (only 1 package key, rest are not)
        return [
            'doctrine/dbal' => 'Database abstraction layer description',
            'description' => 'Main description',
            'type' => 'library',
        ];
    }
}

?>
-----
<?php

class RegularArrayTest
{
    public function getData()
    {
        // This should NOT be changed - it's not a composer dependency array
        return [
            'laravel/framework' => 'Some random value',
            'name' => 'John Doe',
        ];
    }

    public function getMapping()
    {
        // Single package-like key - should NOT be changed (needs 2+ to be composer-like)
        return [
            'some/package' => 'value',
            'regular_key' => 'value2',
        ];
    }

    public function getDescriptions()
    {
        // Array with package names as keys but clearly not a composer array (only 1 package key, rest are not)
        return [
            'doctrine/dbal' => 'Database abstraction layer description',
            'description' => 'Main description',
            'type' => 'library',
        ];
    }
}

?>
