<?php

namespace App\Dates;

use Carbon\Carbon;

final class ChainStripper
{
    public function format(Carbon $date): string
    {
        return $date->setUtf8()->format('Y-m-d');
    }
}

?>
-----
<?php

namespace App\Dates;

use Carbon\Carbon;

final class ChainStripper
{
    public function format(Carbon $date): string
    {
        return $date->format('Y-m-d');
    }
}

?>
