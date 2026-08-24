<?php

namespace App\Fixtures\Laravel11\UpdateEnumerableContractRector;

use Illuminate\Support\Enumerable as EnumerableContract;

class LegacyEnumerableCollection implements EnumerableContract
{
    public function dump()
    {
        return $this;
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateEnumerableContractRector;

use Illuminate\Support\Enumerable as EnumerableContract;

class LegacyEnumerableCollection implements EnumerableContract
{
    public function dump(...$args)
    {
        return $this;
    }
}

?>
