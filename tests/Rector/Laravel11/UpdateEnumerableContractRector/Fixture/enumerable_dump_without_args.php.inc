<?php

use Illuminate\Support\Enumerable;

class CustomCollection implements Enumerable
{
    public function dump()
    {
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
        dd($this->toArray());
    }

    public function toArray()
    {
        return ['data' => 'example'];
    }
}

?>
