<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\E2E;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\E2E\E2eApplicationDirectoryAllocator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class E2eApplicationDirectoryAllocatorTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        $temporaryRoot = sys_get_temp_dir().'/e2e-directory-allocator-'.bin2hex(random_bytes(6));
        mkdir($temporaryRoot, 0700, true);
        $resolvedRoot = realpath($temporaryRoot);

        if (! is_string($resolvedRoot)) {
            throw new \RuntimeException('Could not resolve the allocator test root.');
        }

        $this->temporaryRoot = $resolvedRoot;
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->temporaryRoot)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->temporaryRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($this->temporaryRoot);
    }

    public function test_same_transition_retries_an_atomically_reserved_candidate(): void
    {
        $suffixes = ['same', 'same', 'next'];
        $allocator = new E2eApplicationDirectoryAllocator(
            $this->temporaryRoot,
            static function () use (&$suffixes): string {
                return array_shift($suffixes) ?? 'exhausted';
            },
        );

        $first = $allocator->allocate(10, 11);
        $second = $allocator->allocate(10, 11);

        self::assertSame($this->temporaryRoot.'/e2e-10-11-same', $first);
        self::assertSame($this->temporaryRoot.'/e2e-10-11-next', $second);
        self::assertNotSame($first, $second);
        self::assertFileDoesNotExist($first);
        self::assertFileExists($first.'.lock');
        self::assertFileExists($second.'.lock');

        $allocator->release($first);
        $allocator->release($second);

        self::assertFileDoesNotExist($first.'.lock');
        self::assertFileDoesNotExist($second.'.lock');
    }

    public function test_non_owner_cannot_release_another_allocator_reservation(): void
    {
        $allocator = new E2eApplicationDirectoryAllocator(
            $this->temporaryRoot,
            static fn (): string => 'owned',
        );
        $otherAllocator = new E2eApplicationDirectoryAllocator(
            $this->temporaryRoot,
            static fn (): string => 'owned',
        );
        $directory = $allocator->allocate(10, 11);

        $otherAllocator->release($directory);

        self::assertFileExists($directory.'.lock');
        $allocator->release($directory);
        self::assertFileDoesNotExist($directory.'.lock');
    }

    public function test_preexisting_files_and_dangling_symlinks_are_skipped(): void
    {
        $occupied = $this->temporaryRoot.'/e2e-10-11-occupied';
        file_put_contents($occupied, 'occupied');
        $suffixes = ['occupied', 'available'];

        $allocator = new E2eApplicationDirectoryAllocator(
            $this->temporaryRoot,
            static function () use (&$suffixes): string {
                return array_shift($suffixes) ?? 'exhausted';
            },
        );
        $available = $allocator->allocate(10, 11);
        $allocator->release($available);

        self::assertSame($this->temporaryRoot.'/e2e-10-11-available', $available);

        if (! function_exists('symlink')) {
            self::markTestSkipped('The platform does not support symlinks.');
        }

        $dangling = $this->temporaryRoot.'/e2e-10-11-dangling';
        self::assertTrue(symlink($this->temporaryRoot.'/does-not-exist', $dangling));
        $suffixes = ['dangling', 'after-dangling'];
        $symlinkAllocator = new E2eApplicationDirectoryAllocator(
            $this->temporaryRoot,
            static function () use (&$suffixes): string {
                return array_shift($suffixes) ?? 'exhausted';
            },
        );
        $afterDangling = $symlinkAllocator->allocate(10, 11);

        self::assertSame($this->temporaryRoot.'/e2e-10-11-after-dangling', $afterDangling);
        $symlinkAllocator->release($afterDangling);
    }

    public function test_invalid_temporary_roots_are_rejected(): void
    {
        $this->expectExceptionMessage('must be an absolute path');
        new E2eApplicationDirectoryAllocator('relative-root');
    }

    public function test_missing_temporary_root_is_rejected(): void
    {
        $this->expectExceptionMessage('must be an existing directory');
        new E2eApplicationDirectoryAllocator($this->temporaryRoot.'/missing');
    }

    public function test_independent_processes_contend_for_the_same_candidate(): void
    {
        $worker = $this->temporaryRoot.'/allocator-worker.php';
        $autoload = dirname(__DIR__, 3).'/vendor/autoload.php';
        $workerContents = <<<'PHP'
<?php

require $argv[3];

$suffixes = ['same', 'second-'.$argv[2]];
$allocator = new \MuhammadSadeeq\LaravelUpgradesRector\Upgrade\E2E\E2eApplicationDirectoryAllocator(
    $argv[1],
    static function () use (&$suffixes): string {
        return array_shift($suffixes) ?? 'exhausted';
    },
);
$directory = $allocator->allocate(10, 11);
file_put_contents($argv[1].'/'.$argv[2].'.reserved', $directory);

if ($argv[2] === 'a') {
    usleep(1000000);
}

$allocator->release($directory);
PHP;
        file_put_contents($worker, $workerContents);

        $processA = new Process([PHP_BINARY, $worker, $this->temporaryRoot, 'a', $autoload]);
        $processA->setTimeout(5);
        $processA->start();

        $ready = $this->temporaryRoot.'/a.reserved';
        $deadline = microtime(true) + 3;

        while (! is_file($ready) && microtime(true) < $deadline) {
            usleep(10000);
        }

        self::assertFileExists($ready, $processA->getErrorOutput());

        $processB = new Process([PHP_BINARY, $worker, $this->temporaryRoot, 'b', $autoload]);
        $processB->setTimeout(5);
        $processB->run();
        $processA->wait();

        self::assertSame(0, $processA->getExitCode(), $processA->getErrorOutput());
        self::assertSame(0, $processB->getExitCode(), $processB->getErrorOutput());
        self::assertSame(
            $this->temporaryRoot.'/e2e-10-11-same',
            trim((string) file_get_contents($this->temporaryRoot.'/a.reserved')),
        );
        self::assertSame(
            $this->temporaryRoot.'/e2e-10-11-second-b',
            trim((string) file_get_contents($this->temporaryRoot.'/b.reserved')),
        );
    }
}
