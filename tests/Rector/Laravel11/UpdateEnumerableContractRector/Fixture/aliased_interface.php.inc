<?php

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

use Illuminate\Support\Enumerable as EnumerableContract;

class LegacyEnumerableCollection implements EnumerableContract
{
    public function dump(...$args)
    {
        return $this;
    }
}

?>
