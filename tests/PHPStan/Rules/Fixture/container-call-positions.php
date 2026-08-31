<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Container\Container;

function returnContainerCall(Container $container): mixed
{
    // The return expression is one valid call position.
    return $container->call(static fn (): string => 'value');
}

function passContainerCall(Container $container): void
{
    consumeContainerValue($container->call(static fn (): string => 'value'));
}

function consumeContainerValue(mixed $value): void {}
