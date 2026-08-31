<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Support\Str;

function configureUuidFactory(): void
{
    Str::createUuidsNormally();
}

function configureUlidFactory(): mixed
{
    return consumeFactoryResult(Str::createUlidsUsing(static fn (): mixed => null));
}

function consumeFactoryResult(mixed $value): mixed
{
    return $value;
}
