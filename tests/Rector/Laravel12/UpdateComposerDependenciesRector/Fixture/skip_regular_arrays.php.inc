<?php

class RegularArrayTest
{
    public function getData()
    {
        // This should NOT be changed - no matching package keys
        return [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];
    }

    public function getMapping()
    {
        // Non-package keys should NOT be changed
        return [
            'some/package' => 'value',
            'regular_key' => 'value2',
        ];
    }
}

?>
