<?php

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules\Fixture;

use Psr\Log\LoggerInterface;

final class ContainerNullableListener
{
    public function __construct(?LoggerInterface $logger = null) {}

    public function handle(): void {}
}
