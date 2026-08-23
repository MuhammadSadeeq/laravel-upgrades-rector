<?php

namespace App\Dates;

use Carbon\Carbon;

final class Creator
{
    public function build(): array
    {
        $date1 = Carbon::create(2023, 1, 1, 12, 0, 0, tz: 'UTC');
        $date2 = Carbon::createFromFormat('Y-m-d', '2023-01-01', tz: 'America/New_York');

        // Already using the new parameter name
        $date3 = Carbon::create(2023, 1, 1, timezone: 'UTC');

        return [$date1, $date2, $date3];
    }
}

?>
-----
<?php

namespace App\Dates;

use Carbon\Carbon;

final class Creator
{
    public function build(): array
    {
        $date1 = Carbon::create(2023, 1, 1, 12, 0, 0, timezone: 'UTC');
        $date2 = Carbon::createFromFormat('Y-m-d', '2023-01-01', timezone: 'America/New_York');

        // Already using the new parameter name
        $date3 = Carbon::create(2023, 1, 1, timezone: 'UTC');

        return [$date1, $date2, $date3];
    }
}

?>
