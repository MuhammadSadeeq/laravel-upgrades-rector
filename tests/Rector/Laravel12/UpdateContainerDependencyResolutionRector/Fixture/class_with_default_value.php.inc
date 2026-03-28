<?php

namespace App\Models;

use Carbon\Carbon;

class Example
{
    public function __construct(public ?Carbon $date = null)
    {
    }
}

?>
-----
<?php

namespace App\Models;

use Carbon\Carbon;

class Example
{
    // Laravel 12: Container now respects default parameter values when resolving dependencies. This constructor may behave differently.
    public function __construct(public ?Carbon $date = null)
    {
    }
}

?>
