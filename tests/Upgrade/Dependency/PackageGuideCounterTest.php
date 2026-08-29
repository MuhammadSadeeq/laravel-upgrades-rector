<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\PackageGuideCounter;
use PHPUnit\Framework\TestCase;

final class PackageGuideCounterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/package-counter-'.bin2hex(random_bytes(5));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_extension_matching_uses_dot_boundaries_and_skips_symlinks(): void
    {
        mkdir($this->directory.'/app', 0777, true);
        file_put_contents($this->directory.'/app/real.php', '<?php');
        file_put_contents($this->directory.'/app/view.blade.php', '<div />');
        file_put_contents($this->directory.'/app/notphp', '<?php');
        file_put_contents($this->directory.'/app/readme.php.txt', '');

        $outside = sys_get_temp_dir().'/package-counter-outside-'.bin2hex(random_bytes(5));
        mkdir($outside, 0777, true);
        file_put_contents($outside.'/outside.php', '<?php');

        if (! symlink($outside.'/outside.php', $this->directory.'/app/outside.php')) {
            $this->removeDirectory($outside);
            self::markTestSkipped('Symlinks are not available in this environment.');
        }

        // This cycle must not recurse because symlinks are never followed.
        symlink($this->directory.'/app', $this->directory.'/app/cycle');

        $counter = new PackageGuideCounter('source files', ['app'], ['.php', '.blade.php']);

        try {
            self::assertSame(2, $counter->count($this->directory));
        } finally {
            $this->removeDirectory($outside);
        }
    }

    public function test_file_and_depth_bounds_are_deterministic(): void
    {
        mkdir($this->directory.'/app/deep/deeper', 0777, true);
        file_put_contents($this->directory.'/app/direct.php', '');
        file_put_contents($this->directory.'/app/deep/one.php', '');
        file_put_contents($this->directory.'/app/deep/deeper/two.php', '');

        self::assertSame(
            1,
            (new PackageGuideCounter('files', ['app'], ['php'], 1, 32))->count($this->directory),
        );
        self::assertSame(
            1,
            (new PackageGuideCounter('files', ['app'], ['php'], 100, 0))->count($this->directory),
        );
    }

    public function test_invalid_or_unreadable_paths_are_skipped_without_throwing(): void
    {
        $counter = new PackageGuideCounter(
            'files',
            ['.', 'missing', 'app\\Livewire', 'C:/app', ' C:/app ', 'C:\\app', '\\\\server\\share', '//server/share'],
            ['', '.', '.php', 'php/'],
        );

        self::assertSame(0, $counter->count($this->directory));
    }

    public function test_unsupported_entries_consume_a_separate_bounded_inspection_budget(): void
    {
        mkdir($this->directory.'/app', 0777, true);
        file_put_contents($this->directory.'/app/00.php', '<?php');

        for ($index = 1; $index <= 20; $index++) {
            file_put_contents($this->directory.'/app/'.sprintf('%02d.txt', $index), 'not a source file');
        }

        // maxFiles limits matching files; maxEntries prevents an unbounded
        // scan of unrelated files. The bounded traversal is repeatable for
        // the same directory state and can count no more than the one match.
        $counter = new PackageGuideCounter('files', ['app'], ['php'], 100, 32, 2);
        $count = $counter->count($this->directory);

        self::assertLessThanOrEqual(1, $count);
        self::assertSame($count, $counter->count($this->directory));
        self::assertSame(
            1,
            (new PackageGuideCounter('files', ['app/00.php'], ['php'], 100, 32, 1))->count($this->directory),
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! is_link($directory)) {
            return;
        }

        if (is_link($directory)) {
            @unlink($directory);

            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            if (is_link($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
