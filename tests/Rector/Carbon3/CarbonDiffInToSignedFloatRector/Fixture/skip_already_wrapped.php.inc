<?php

namespace App\Reports;

use Carbon\Carbon;

final class AlreadyWrapped
{
    public function gaps(Carbon $start, Carbon $end): array
    {
        // Already wrapped calls must not be double-wrapped on a second run
        $a = (int) $start->diffInDays($end);
        $b = abs($start->diffInHours($end));
        $c = (int) abs($end->diffInSeconds($start, false));
        $d = floor($start->diffInWeeks($end));

        return [$a, $b, $c, $d];
    }
}

?>
