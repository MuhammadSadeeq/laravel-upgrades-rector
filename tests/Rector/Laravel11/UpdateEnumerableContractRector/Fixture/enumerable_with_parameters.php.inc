<?php

use Illuminate\Support\Enumerable;

class ParameterizedCollection implements Enumerable
{
    public function dump($format = 'array')
    {
        dd($this->toArray());
    }

    public function toArray()
    {
        return ['key' => 'value'];
    }
}

?>
-----
<?php

use Illuminate\Support\Enumerable;

// Laravel 11: Enumerable::dump() signature changed to dump(...$args). Update this method signature manually.
class ParameterizedCollection implements Enumerable
{
    public function dump($format = 'array')
    {
        dd($this->toArray());
    }

    public function toArray()
    {
        return ['key' => 'value'];
    }
}

?>
