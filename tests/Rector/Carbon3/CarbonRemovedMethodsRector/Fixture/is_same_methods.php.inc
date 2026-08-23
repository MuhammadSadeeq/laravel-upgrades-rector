<?php

namespace App\Dates;

use Carbon\Carbon;

final class Comparisons
{
    public function check(Carbon $date, Carbon $other): array
    {
        $sameDay = $date->isSameDay();
        $sameMonth = $date->isSameMonth();
        $sameHour = $date->isSameHour();
        $sameMillennium = $date->isSameMillennium();

        // With an argument isSame*() keeps its meaning and name
        $sameWithOther = $date->isSameDay($other);
        $sameAs = $date->isSameAs('Y-m-d', $other);

        return [$sameDay, $sameMonth, $sameHour, $sameMillennium, $sameWithOther, $sameAs];
    }
}

?>
-----
<?php

namespace App\Dates;

use Carbon\Carbon;

final class Comparisons
{
    public function check(Carbon $date, Carbon $other): array
    {
        $sameDay = $date->isCurrentDay();
        $sameMonth = $date->isCurrentMonth();
        $sameHour = $date->isCurrentHour();
        $sameMillennium = $date->isCurrentMillennium();

        // With an argument isSame*() keeps its meaning and name
        $sameWithOther = $date->isSameDay($other);
        $sameAs = $date->isSameAs('Y-m-d', $other);

        return [$sameDay, $sameMonth, $sameHour, $sameMillennium, $sameWithOther, $sameAs];
    }
}

?>
