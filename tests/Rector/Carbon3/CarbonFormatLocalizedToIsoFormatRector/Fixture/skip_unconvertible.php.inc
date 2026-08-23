<?php

namespace App\Reports;

use Carbon\Carbon;

final class Unconvertible
{
    public function render(Carbon $date, string $format): array
    {
        // %C has no isoFormat equivalent — the whole call must stay untouched
        $century = $date->formatLocalized('%C');

        // Non-literal formats cannot be converted safely
        $dynamic = $date->formatLocalized($format);

        return [$century, $dynamic];
    }
}

?>
