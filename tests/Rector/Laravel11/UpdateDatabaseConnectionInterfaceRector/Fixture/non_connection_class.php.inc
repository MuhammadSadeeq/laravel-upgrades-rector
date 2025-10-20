<?php

class RegularClass
{
    public function someMethod()
    {
        return 'regular method';
    }

    public function scalar($value)
    {
        // This scalar method should not be affected
        return (string) $value;
    }
}

?>