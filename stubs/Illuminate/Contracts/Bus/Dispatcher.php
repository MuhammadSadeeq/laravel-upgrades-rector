<?php

namespace Illuminate\Contracts\Bus;

interface Dispatcher
{
    public function dispatch(mixed $command): mixed;

    public function dispatchSync(mixed $command, mixed $handler = null): mixed;

    public function dispatchNow(mixed $command, mixed $handler = null): mixed;

    public function hasCommandHandler(mixed $command): bool;

    public function getCommandHandler(mixed $command): mixed;

    public function pipeThrough(array $pipes): static;

    public function map(array $map): static;

    public function dispatchAfterResponse(mixed $command, mixed $handler = null): mixed;
}
