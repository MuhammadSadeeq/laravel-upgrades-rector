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
}

?>
