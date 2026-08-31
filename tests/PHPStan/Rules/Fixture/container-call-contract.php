<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Contracts\Container\Container;

function callThroughContainerContract(Container $container): mixed
{
    return $container->call(static fn (): string => 'value');
}
