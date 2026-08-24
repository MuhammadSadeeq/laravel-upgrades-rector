<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Rector\Laravel12\ReplaceHasVersion7UuidsRector;

use Iterator;
use MuhammadSadeeq\LaravelUpgradesRector\Tests\Support\AbstractUpgradeRectorTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ReplaceHasVersion7UuidsRectorTest extends AbstractUpgradeRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__.'/Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__.'/config/configured_rule.php';
    }
}
