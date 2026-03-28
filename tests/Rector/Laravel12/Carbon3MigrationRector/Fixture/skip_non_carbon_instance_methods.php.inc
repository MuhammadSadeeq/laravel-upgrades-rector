<?php

namespace App\Services;

class CustomDiffer
{
    public function diffInSeconds(mixed $other): int
    {
        return 42;
    }

    public function isSameDay(): bool
    {
        return true;
    }

    public function formatLocalized(string $format): string
    {
        return $format;
    }
}

function testNonCarbonCalls()
{
    $differ = new CustomDiffer();

    $result = $differ->diffInSeconds($other);
    $same = $differ->isSameDay();
    $fmt = $differ->formatLocalized('%A');

    return [$result, $same, $fmt];
}

?>
