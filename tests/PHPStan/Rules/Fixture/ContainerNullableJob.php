<?php

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules\Fixture;

use Illuminate\Contracts\Queue\ShouldQueue;
use Psr\Log\LoggerInterface;

final class ContainerNullableJob implements ShouldQueue
{
    public function __construct(?LoggerInterface $logger = null) {}

    public function handle(): void {}
}
