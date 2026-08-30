<?php

namespace App;

final class TestClock
{
    public static function setTestNow(string $value): void {}
}

function freezeOtherClock(): void
{
    TestClock::setTestNow('2024-01-01');
}
