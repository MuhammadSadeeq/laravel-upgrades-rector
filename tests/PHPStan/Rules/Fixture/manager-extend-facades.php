<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;

function extendAuthFacade(): void
{
    Auth::extend('custom', static fn (): null => null);
}

function extendCacheFacadeAsArgument(): mixed
{
    return consumeManagerExtension(Cache::extend('custom', static fn (): null => null));
}

function extendRedisFacade(): void
{
    Redis::extend('custom', static fn (): null => null);
}

function extendNotificationFacadeAsArgument(): mixed
{
    return consumeManagerExtension(Notification::extend('custom', static fn (): null => null));
}

function consumeManagerExtension(mixed $extension): mixed
{
    return $extension;
}
