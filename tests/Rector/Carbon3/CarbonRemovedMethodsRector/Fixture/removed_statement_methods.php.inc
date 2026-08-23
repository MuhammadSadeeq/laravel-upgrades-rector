<?php

namespace App\Dates;

use Carbon\Carbon;

final class WeekConfig
{
    public function configure(Carbon $date): int
    {
        // Calling any of these methods would fatal under Carbon 3, so the
        // standalone statements are removed entirely.
        $date->setUtf8();
        $date->setWeekStartsAt(1);
        $date->setWeekEndsAt(0);

        return $date->isoFormat('u');
    }
}

?>
-----
<?php

namespace App\Dates;

use Carbon\Carbon;

final class WeekConfig
{
    public function configure(Carbon $date): int
    {
        return $date->isoFormat('u');
    }
}

?>
