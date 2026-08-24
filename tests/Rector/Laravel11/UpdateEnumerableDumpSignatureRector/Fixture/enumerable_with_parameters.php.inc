<?php

namespace App\Fixtures\Laravel11\UpdateEnumerableContractRector;

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

namespace App\Fixtures\Laravel11\UpdateEnumerableContractRector;

use Illuminate\Support\Enumerable;

// @laravel-upgrade enumerable-dump Enumerable::dump() signature changed to dump(...$args). Update this method signature manually.
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
