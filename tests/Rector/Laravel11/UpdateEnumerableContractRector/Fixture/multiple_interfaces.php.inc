<?php

namespace App\Fixtures\Laravel11\UpdateEnumerableContractRector;

use Illuminate\Support\Enumerable;
use Countable;

class MultiCollection implements Countable, Enumerable
{
    public function dump()
    {
        var_dump($this->items);
    }

    public function count(): int
    {
        return 0;
    }

    private $items = [];
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateEnumerableContractRector;

use Illuminate\Support\Enumerable;
use Countable;

class MultiCollection implements Countable, Enumerable
{
    public function dump(...$args)
    {
        var_dump($this->items);
    }

    public function count(): int
    {
        return 0;
    }

    private $items = [];
}

?>
