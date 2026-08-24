<?php

namespace App\Fixtures\Laravel11\UpdateRateLimitingRector;

class CustomJob
{
    public int $decayMinutes = 10;

    public function handle()
    {
        $job = new CustomJob();
        echo $job->decayMinutes;
    }
}

?>
