<?php

namespace Carbon;

class CarbonImmutable implements CarbonInterface
{
    public static function now(): static
    {
        return new static();
    }

    public static function createFromTimestamp(int|float $timestamp, mixed $timezone = null): static
    {
        return new static();
    }

    public static function createFromTimestampUTC(int|float $timestamp): static
    {
        return new static();
    }

    public static function minValue(): static
    {
        return new static();
    }

    public static function maxValue(): static
    {
        return new static();
    }

    public static function startOfTime(): static
    {
        return new static();
    }

    public static function endOfTime(): static
    {
        return new static();
    }

    public function addHours(int $hours = 1): static
    {
        return $this;
    }

    public function addDay(): static
    {
        return $this;
    }

    public function diffInSeconds(self $other = null): float
    {
        return 0.0;
    }

    public function diffInMinutes(self $other = null): float
    {
        return 0.0;
    }

    public function diffInHours(self $other = null): float
    {
        return 0.0;
    }

    public function diffInDays(self $other = null): float
    {
        return 0.0;
    }

    public function diffInWeeks(self $other = null): float
    {
        return 0.0;
    }

    public function diffInMonths(self $other = null): float
    {
        return 0.0;
    }

    public function diffInYears(self $other = null): float
    {
        return 0.0;
    }

    public function diffInMilliseconds(self $other = null): float
    {
        return 0.0;
    }

    public function diffInMicroseconds(self $other = null): float
    {
        return 0.0;
    }

    public function isSameDay(self $other = null): bool
    {
        return true;
    }

    public function isSameMonth(self $other = null): bool
    {
        return true;
    }

    public function formatLocalized(string $format): string
    {
        return '';
    }

    public function isoFormat(string $format): string
    {
        return '';
    }

    public function setUtf8(bool $utf8 = true): static
    {
        return $this;
    }

    public function setWeekStartsAt(int $day): void
    {
    }

    public function setWeekEndsAt(int $day): void
    {
    }
}
