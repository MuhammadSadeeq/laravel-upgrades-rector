<?php

namespace App\Reports;

use Carbon\Carbon;

final class DiffMethods
{
    public function describe(Carbon $start, Carbon $end): array
    {
        return [
            // Carbon 2 default was absolute int; keep that behaviour
            'seconds' => $end->diffInSeconds($start),
            'days' => $end->diffInDays($start),
            'hours' => $end->diffInHours($start),
            'signedHours' => $end->diffInHours($start, false),
            'explicitAbsolute' => $end->diffInMinutes($start, true),
            'weekdays' => $start->diffInWeekdays($end),
        ];
    }
}

?>
-----
<?php

namespace App\Reports;

use Carbon\Carbon;

final class DiffMethods
{
    public function describe(Carbon $start, Carbon $end): array
    {
        return [
            // Carbon 2 default was absolute int; keep that behaviour
            'seconds' => (int) abs($end->diffInSeconds($start)),
            'days' => (int) abs($end->diffInDays($start)),
            'hours' => (int) abs($end->diffInHours($start)),
            'signedHours' => (int) $end->diffInHours($start, false),
            'explicitAbsolute' => (int) abs($end->diffInMinutes($start)),
            'weekdays' => abs($start->diffInWeekdays($end)),
        ];
    }
}

?>
