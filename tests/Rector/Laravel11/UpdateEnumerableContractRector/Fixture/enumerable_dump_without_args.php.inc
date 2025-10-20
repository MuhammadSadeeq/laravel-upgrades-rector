<?php

use Illuminate\Support\Enumerable;

class CustomCollection implements Enumerable
{
    public function dump()
    {
        // Current implementation without arguments
        dd($this->toArray());
    }

    public function toArray()
    {
        return ['data' => 'example'];
    }
}

?>
-----
<?php

use Illuminate\Support\Enumerable;

class CustomCollection implements Enumerable
{
    public function dump(...$args)
    {
        // Current implementation without arguments
        dd($this->toArray());
    }

    public function toArray()
    {
        return ['data' => 'example'];
    }
}

?>