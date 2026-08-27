<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\FileClassifier;
use PHPUnit\Framework\TestCase;

final class FileClassifierTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/classifier-'.bin2hex(random_bytes(6));
        mkdir($this->tmpDir.'/from', 0777, true);
        mkdir($this->tmpDir.'/to', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->tmpDir);
    }

    public function test_classifies_added_removed_modified_and_renamed_files(): void
    {
        $this->write('from/unchanged.txt', 'same');
        $this->write('from/old.txt', 'renamed');
        $this->write('from/removed.txt', 'removed');
        $this->write('from/changed.txt', 'before');
        $this->write('to/unchanged.txt', 'same');
        $this->write('to/new.txt', 'new');
        $this->write('to/new-name.txt', 'renamed');
        $this->write('to/changed.txt', 'after');

        $result = (new FileClassifier)->classify($this->tmpDir.'/from', $this->tmpDir.'/to');

        self::assertSame(['new.txt'], $result['added']);
        self::assertSame(['removed.txt'], $result['removed']);
        self::assertSame(['changed.txt'], $result['modified']);
        self::assertSame(['old.txt' => 'new-name.txt'], $result['renamed']);
    }

    private function write(string $relative, string $contents): void
    {
        $path = $this->tmpDir.'/'.$relative;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
    }

    private function remove(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
