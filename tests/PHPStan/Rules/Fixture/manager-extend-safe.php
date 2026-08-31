<?php

namespace App\Laravel13RuleFixtures;

final class ExtendUnrelatedService
{
    public function extend(string $name, callable $callback): mixed
    {
        return $callback();
    }
}

final class Notification
{
    public static function extend(string $name, callable $callback): mixed
    {
        return $callback();
    }
}

final class Redis
{
    public static function extend(string $name, callable $callback): mixed
    {
        return $callback();
    }
}

function extendUnrelatedService(ExtendUnrelatedService $service): mixed
{
    return $service->extend('custom', static fn (): null => null);
}

function callUnrelatedStaticExtend(): mixed
{
    return ExtendUnrelatedService::extend('custom', static fn (): null => null);
}

function callUnrelatedNotificationAlias(): mixed
{
    return Notification::extend('custom', static fn (): null => null);
}

function callUnrelatedRedisAlias(): mixed
{
    return Redis::extend('custom', static fn (): null => null);
}
