<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Rector\Laravel13\CsrfMiddleware;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use MuhammadSadeeq\LaravelUpgradesRector\Tests\Support\AbstractUpgradeRectorTestCase;

/**
 * Laravel 13 CSRF middleware + bootstrap/app.php renames, combining core
 * RenameClassRector (configured in the Laravel 13 set) with the method rule.
 */
final class CsrfMiddlewareTest extends AbstractUpgradeRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_rule.php';
    }
}
