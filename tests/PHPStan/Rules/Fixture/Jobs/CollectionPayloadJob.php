<?php

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules\Fixture\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;

final class CollectionPayloadJob implements ShouldQueue
{
    public Collection $users;

    public function __construct(
        public ?Collection $nullableUsers,
        public Collection|\Traversable $unionUsers,
    ) {}
}
