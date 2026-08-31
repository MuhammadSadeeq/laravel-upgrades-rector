<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Cache\CacheManager;

function extendCacheManager(CacheManager $manager): mixed
{
    return $manager->extend('custom', static fn (): null => null);
}
