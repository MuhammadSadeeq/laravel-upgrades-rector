<?php

namespace App\Reports;

use Carbon\Carbon;

final class DynamicAbsolute
{
    public function gaps(Carbon $start, Carbon $end, bool $flag, array $options): array
    {
        // Non-literal or spread absolute arguments cannot be decided safely
        $a = $end->diffInDays($start, $flag);
        $b = $end->diffInHours($start, absolute: $flag);
        $c = $end->diffInMinutes($start, ...$options);

        return [$a, $b, $c];
    }
}

?>
