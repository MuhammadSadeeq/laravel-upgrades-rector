<?php

namespace App\Cache;

use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\Store;

// ArrayStore already provides a concrete touch(); the contract rule must not
// append a duplicate.
class CustomStore extends ArrayStore implements Store
{
}

?>
-----
<?php

namespace App\Cache;

use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\Store;

// ArrayStore already provides a concrete touch(); the contract rule must not
// append a duplicate.
class CustomStore extends ArrayStore implements Store
{
}

?>
