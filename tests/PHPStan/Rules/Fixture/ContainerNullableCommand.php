<?php

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules\Fixture;

use Illuminate\Console\Command;
use Psr\Log\LoggerInterface;

final class ContainerNullableCommand extends Command
{
    public function __construct(?LoggerInterface $logger = null) {}
}
