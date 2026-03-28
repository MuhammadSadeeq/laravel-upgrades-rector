<?php

use Carbon\Carbon;

class AlreadyTransformedTest
{
    public function testIdempotency()
    {
        $date1 = Carbon::now();
        $date2 = Carbon::now()->addDay();

        // These are already transformed - should NOT be wrapped again
        $days = (int) abs($date1->diffInDays($date2));
        $hours = (int) abs($date1->diffInHours($date2));
        $seconds = (int) abs($date1->diffInSeconds($date2));

        return [$days, $hours, $seconds];
    }
}

?>
-----
<?php

use Carbon\Carbon;

class AlreadyTransformedTest
{
    public function testIdempotency()
    {
        $date1 = Carbon::now();
        $date2 = Carbon::now()->addDay();

        // These are already transformed - should NOT be wrapped again
        $days = (int) abs($date1->diffInDays($date2));
        $hours = (int) abs($date1->diffInHours($date2));
        $seconds = (int) abs($date1->diffInSeconds($date2));

        return [$days, $hours, $seconds];
    }
}

?>
