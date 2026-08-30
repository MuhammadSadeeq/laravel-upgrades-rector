<?php

namespace App;

final class OtherDate
{
    public function diffInDays(): int
    {
        return 0;
    }

    public function format(): string
    {
        return 'today';
    }
}

function inspectOtherDate(OtherDate $date): void
{
    $date->diffInDays();
    $date->format();
}
