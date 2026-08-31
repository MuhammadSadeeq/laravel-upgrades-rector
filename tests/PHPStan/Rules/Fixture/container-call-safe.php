<?php

namespace App\Laravel13RuleFixtures;

final class ContainerCallUnrelatedService
{
    public function call(callable $callback): mixed
    {
        return $callback();
    }
}

function unrelatedContainerCall(ContainerCallUnrelatedService $service): mixed
{
    return $service->call(static fn (): string => 'value');
}

function nonVariableContainerCall(): mixed
{
    return (new ContainerCallUnrelatedService)->call(static fn (): string => 'value');
}
