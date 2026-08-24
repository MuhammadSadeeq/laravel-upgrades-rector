<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Rector\Laravel13\UpdateCacheContracts;

use Iterator;
use MuhammadSadeeq\LaravelUpgradesRector\Tests\Support\AbstractUpgradeRectorTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A class implementing both cache contracts must receive exactly ONE touch()
 * method: the second rule sees the method appended by the first and skips.
 */
final class UpdateCacheContractsTest extends AbstractUpgradeRectorTestCase
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
