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
            'immutableMax' => CarbonImmutable::maxValue(),
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
            'min' => Carbon::minValue(),
            'max' => Carbon::maxValue(),
            'immutableMin' => CarbonImmutable::startOfTime(),
            'immutableMax' => CarbonImmutable::endOfTime(),
        ];
    }
}

?>
