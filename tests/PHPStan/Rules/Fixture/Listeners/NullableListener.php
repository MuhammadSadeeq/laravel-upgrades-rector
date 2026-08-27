<?php

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules\Fixture\Listeners;

use Psr\Log\LoggerInterface;

final class NullableListener
{
    public function __construct(?LoggerInterface $logger = null) {}

    public function handle(): void {}
}
