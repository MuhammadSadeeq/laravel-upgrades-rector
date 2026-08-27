<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Console;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\ToCommand;
use PHPUnit\Framework\TestCase;

final class ToCommandConfigPathTest extends TestCase
{
    public function test_advisory_config_path_uses_packaged_neon_filename(): void
    {
        $path = ToCommand::advisoryConfigPath(11);

        self::assertStringEndsWith('/resources/phpstan/upgrade-11.neon', $path);
        self::assertFileExists($path);
        self::assertFileDoesNotExist(str_replace('.neon', '.phpstan.neon', $path));
    }
}
