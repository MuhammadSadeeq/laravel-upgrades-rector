<?php

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules\Fixture\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

final class CollectionSafeJob implements ShouldQueue
{
    public Collection $users;

    public function __construct(public ?Collection $nullableUsers = null) {}
}
