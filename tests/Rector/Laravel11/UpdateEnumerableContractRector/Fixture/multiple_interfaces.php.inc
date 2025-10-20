<?php

use Illuminate\Support\Enumerable;
use SomeOther\Interface as SomeInterface;

class MultipleInterfacesCollection implements SomeInterface, Enumerable
{
    public function dump()
    {
        // This should be updated since it implements Enumerable
        var_dump($this->items);
    }

    public function someInterfaceMethod()
    {
        return 'implementation';
    }

    public function toArray()
    {
        return $this->items;
    }

    private $items = ['a', 'b', 'c'];
}

?>
-----
<?php

use Illuminate\Support\Enumerable;
use SomeOther\Interface as SomeInterface;

class MultipleInterfacesCollection implements SomeInterface, Enumerable
{
    public function dump(...$args)
    {
        // This should be updated since it implements Enumerable
        var_dump($this->items);
    }

    public function someInterfaceMethod()
    {
        return 'implementation';
    }

    public function toArray()
    {
        return $this->items;
    }

    private $items = ['a', 'b', 'c'];
}

?>