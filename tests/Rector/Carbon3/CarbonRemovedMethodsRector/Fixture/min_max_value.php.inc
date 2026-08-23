<?php

namespace App\Dates;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

final class Bounds
{
    public function edges(): array
    {
        return [
            'min' => Carbon::minValue(),
            'max' => Carbon::maxValue(),
            'immutableMin' => CarbonImmutable::minValue(),
        ];
    }
}

?>
-----
<?php

namespace App\Dates;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

final class Bounds
{
    public function edges(): array
    {
        return [
            'min' => Carbon::startOfTime(),
            'max' => Carbon::endOfTime(),
            'immutableMin' => CarbonImmutable::startOfTime(),
        ];
    }
}

?>
