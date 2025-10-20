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
    /** Laravel 12: Container now respects default values for constructor parameters. Class-typed parameters with default values will use the default instead of being resolved from the container. */
    public function __construct(public ?Carbon $date = null)
    {
    }
}

?>
