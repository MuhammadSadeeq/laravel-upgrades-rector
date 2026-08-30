<?php

namespace App;

final class HumanDate
{
    public function diffForHumans(): string
    {
        return 'a moment ago';
    }
}

function renderHumanDate(HumanDate $date): string
{
    return $date->diffForHumans();
}
