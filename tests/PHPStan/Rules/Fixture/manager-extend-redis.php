<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Redis\RedisManager;

function extendRedisManager(RedisManager $manager): mixed
{
    return $manager->extend('custom', static fn (): null => null);
}
