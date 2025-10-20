<?php

use Illuminate\Support\Enumerable;

class ParameterizedCollection implements Enumerable
{
    public function dump($format = 'array')
    {
        // Has regular parameters but not variadic - should be updated
        if ($format === 'json') {
            dd(json_encode($this->toArray()));
        } else {
            dd($this->toArray());
        }
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

class ParameterizedCollection implements Enumerable
{
    public function dump(...$args)
    {
        // Has regular parameters but not variadic - should be updated
        if ($format === 'json') {
            dd(json_encode($this->toArray()));
        } else {
            dd($this->toArray());
        }
    }

    public function toArray()
    {
        return ['key' => 'value'];
    }
}

?>