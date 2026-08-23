<?php

namespace App\Reports;

use Carbon\Carbon;

final class Labels
{
    public function render(Carbon $date): array
    {
        $formatted1 = $date->formatLocalized('%A %d %B %Y');
        $formatted2 = $date->formatLocalized('%Y-%m-%d %H:%M:%S');
        $french = $date->formatLocalized('Le %A %d');

        return [$formatted1, $formatted2, $french];
    }
}

?>
-----
<?php

namespace App\Reports;

use Carbon\Carbon;

final class Labels
{
    public function render(Carbon $date): array
    {
        $formatted1 = $date->isoFormat('dddd DD MMMM YYYY');
        $formatted2 = $date->isoFormat('YYYY-MM-DD HH:mm:ss');
        $french = $date->isoFormat('[Le ]dddd DD');

        return [$formatted1, $formatted2, $french];
    }
}

?>
