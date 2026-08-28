<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SkeletonBuilderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/skeleton-builder-'.bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmpDir);
    }

    public function test_arguments_are_strict_and_sorted(): void
    {
        self::assertSame([true, false, [10, 13]], SkeletonBuilder::parseArguments(['13', '--check', '10']));

        $this->expectException(RuntimeException::class);
        SkeletonBuilder::parseArguments(['9']);
    }

    public function test_remote_ref_matching_requires_the_tag_ref(): void
    {
        self::assertTrue(SkeletonBuilder::remoteRefMatches(
            "abc\trefs/tags/v10.3.3\n",
            'v10.3.3',
            'abc',
        ));
        self::assertFalse(SkeletonBuilder::remoteRefMatches("abc\tv10.3.3\n", 'v10.3.3', 'abc'));
        self::assertFalse(SkeletonBuilder::remoteRefMatches("def\trefs/tags/v10.3.3\n", 'v10.3.3', 'abc'));
    }

    public function test_offline_check_is_byte_stable_and_checks_hash_and_size(): void
    {
        $snapshotRoot = $this->tmpDir.'/resources/skeletons';
        mkdir($snapshotRoot, 0700, true);
        $directory = $this->validSnapshot($snapshotRoot, 10);
        $manifest = [
            'schemaVersion' => 1,
            'source' => 'laravel/laravel',
            '10' => SkeletonBuilder::manifestEntry(
                SkeletonBuilder::PINNED_SNAPSHOTS[10]['tag'],
                SkeletonBuilder::PINNED_SNAPSHOTS[10]['commit'],
                '2026-01-01T00:00:00+00:00',
                SkeletonBuilder::directorySize($directory),
                SkeletonBuilder::treeHash($directory),
            ),
        ];
        file_put_contents($snapshotRoot.'/MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
        $before = $this->fileDigest($snapshotRoot);

        self::assertSame(0, (new SkeletonBuilder($this->tmpDir))->run(['--check', '10']));
        self::assertSame($before, $this->fileDigest($snapshotRoot));
    }

    public function test_symlinked_checked_in_files_are_rejected(): void
    {
        $snapshotRoot = $this->tmpDir.'/resources/skeletons';
        mkdir($snapshotRoot, 0700, true);
        $directory = $this->validSnapshot($snapshotRoot, 10);
        symlink($directory.'/composer.json', $directory.'/routes/linked.php');
        $manifest = [
            'schemaVersion' => 1,
            'source' => 'laravel/laravel',
            '10' => SkeletonBuilder::manifestEntry(
                SkeletonBuilder::PINNED_SNAPSHOTS[10]['tag'],
                SkeletonBuilder::PINNED_SNAPSHOTS[10]['commit'],
                '2026-01-01T00:00:00+00:00',
                SkeletonBuilder::directorySize($directory),
                SkeletonBuilder::treeHash($directory),
            ),
        ];
        file_put_contents($snapshotRoot.'/MANIFEST.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        self::assertSame(1, (new SkeletonBuilder($this->tmpDir))->run(['--check', '10']));
    }

    public function test_symlinked_snapshot_root_is_rejected_before_check_or_write(): void
    {
        mkdir($this->tmpDir.'/resources', 0700, true);
        mkdir($this->tmpDir.'/outside', 0700, true);
        file_put_contents($this->tmpDir.'/outside/sentinel', 'untouched');
        symlink($this->tmpDir.'/outside', $this->tmpDir.'/resources/skeletons');

        self::assertSame(1, (new SkeletonBuilder($this->tmpDir))->run(['--check']));
        self::assertSame('untouched', file_get_contents($this->tmpDir.'/outside/sentinel'));
    }

    public function test_symlinked_resources_component_is_rejected_for_run_and_direct_install(): void
    {
        mkdir($this->tmpDir.'/outside', 0700, true);
        file_put_contents($this->tmpDir.'/outside/sentinel', 'untouched');
        mkdir($this->tmpDir.'/resources-placeholder', 0700, true);
        symlink($this->tmpDir.'/outside', $this->tmpDir.'/resources');
        $builder = new SkeletonBuilder($this->tmpDir);

        self::assertSame(1, $builder->run(['--check']));

        try {
            $builder->installTransaction(
                $this->tmpDir.'/resources/skeletons',
                [],
                ['schemaVersion' => 1, 'source' => 'laravel/laravel'],
                $this->tmpDir.'/transaction',
            );
            self::fail('Expected the direct transaction path guard to reject the symlink.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('symlink', strtolower($exception->getMessage()));
        }

        self::assertSame('untouched', file_get_contents($this->tmpDir.'/outside/sentinel'));
    }

    public function test_copy_excludes_paths_and_preserves_executable_modes(): void
    {
        $source = $this->tmpDir.'/source';
        $destination = $this->tmpDir.'/destination';
        mkdir($source.'/node_modules', 0700, true);
        mkdir($source.'/.git', 0700, true);
        file_put_contents($source.'/artisan', "#!/usr/bin/env php\n");
        chmod($source.'/artisan', 0755);
        file_put_contents($source.'/README.md', 'excluded');
        file_put_contents($source.'/node_modules/package.js', 'excluded');
        file_put_contents($source.'/.git/config', 'excluded');

        $builder = new SkeletonBuilder($this->tmpDir);
        $builder->copySnapshot($source, $destination);

        self::assertFileExists($destination.'/artisan');
        self::assertSame(0755, fileperms($destination.'/artisan') & 07777);
        self::assertFileDoesNotExist($destination.'/README.md');
        self::assertFileDoesNotExist($destination.'/node_modules/package.js');
        self::assertFileDoesNotExist($destination.'/.git/config');
    }

    public function test_transaction_rolls_back_all_replacements_when_a_later_install_fails(): void
    {
        $snapshotRoot = $this->tmpDir.'/resources/skeletons';
        mkdir($snapshotRoot, 0700, true);
        $old10 = $this->validSnapshot($snapshotRoot, 10, 'old-10');
        $old11 = $this->validSnapshot($snapshotRoot, 11, 'old-11');
        $manifestPath = $snapshotRoot.'/MANIFEST.json';
        file_put_contents($manifestPath, '{"old":true}\n');
        $temporaryRoot = $this->tmpDir.'/transaction';
        mkdir($temporaryRoot, 0700, true);
        $stage10 = $this->validSnapshot($temporaryRoot, 10, 'new-10');
        $stage11 = $this->validSnapshot($temporaryRoot, 11, 'new-11');
        rename($stage10, $temporaryRoot.'/stage-10');
        rename($stage11, $temporaryRoot.'/stage-11');
        $stage10 = $temporaryRoot.'/stage-10';
        $stage11 = $temporaryRoot.'/stage-11';
        $failed = false;
        $rename = static function (string $from, string $to) use (&$failed): bool {
            if (! $failed && str_ends_with($from, '/10') === false && str_contains($from, '/stage-11')) {
                $failed = true;

                return false;
            }

            return rename($from, $to);
        };
        $builder = new SkeletonBuilder($this->tmpDir, null, $rename);

        try {
            $builder->installTransaction(
                $snapshotRoot,
                [10 => $stage10, 11 => $stage11],
                ['schemaVersion' => 1, 'source' => 'laravel/laravel'],
                $temporaryRoot,
            );
            self::fail('Expected the injected stage failure.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('install Laravel 11', $exception->getMessage());
        }

        self::assertSame('old-10', file_get_contents($old10.'/composer.json'));
        self::assertSame('old-11', file_get_contents($old11.'/composer.json'));
        self::assertSame('{"old":true}\n', file_get_contents($manifestPath));
    }

    public function test_manifest_write_failure_rolls_back_snapshots_and_restores_manifest(): void
    {
        $snapshotRoot = $this->tmpDir.'/resources/skeletons';
        mkdir($snapshotRoot, 0700, true);
        $old = $this->validSnapshot($snapshotRoot, 10, 'old');
        $manifestPath = $snapshotRoot.'/MANIFEST.json';
        file_put_contents($manifestPath, '{"old":true}\n');
        $temporaryRoot = $this->tmpDir.'/transaction';
        mkdir($temporaryRoot, 0700, true);
        $stage = $this->validSnapshot($temporaryRoot, 10, 'new');
        rename($stage, $temporaryRoot.'/stage-10');
        $stage = $temporaryRoot.'/stage-10';
        $rename = static function (string $from, string $to): bool {
            if (str_contains($from, '.skeleton-manifest-') && ! str_contains($to, '/backups/')) {
                return false;
            }

            return rename($from, $to);
        };
        $builder = new SkeletonBuilder($this->tmpDir, null, $rename);

        try {
            $builder->installTransaction(
                $snapshotRoot,
                [10 => $stage],
                ['schemaVersion' => 1, 'source' => 'laravel/laravel'],
                $temporaryRoot,
            );
            self::fail('Expected the injected manifest write failure.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('atomically write manifest', $exception->getMessage());
        }

        self::assertSame('old', file_get_contents($old.'/composer.json'));
        self::assertSame('{"old":true}\n', file_get_contents($manifestPath));
    }

    public function test_injected_rebuild_is_on_project_filesystem_and_preserves_manifest_bytes(): void
    {
        mkdir($this->tmpDir.'/resources/skeletons', 0700, true);
        $pin = SkeletonBuilder::PINNED_SNAPSHOTS[10];
        $runner = static function (array $arguments, int $timeout) use ($pin): string {
            unset($timeout);

            if (($arguments[1] ?? null) === 'clone') {
                $destination = end($arguments);

                if (! is_string($destination)) {
                    throw new RuntimeException('The injected clone command has no destination.');
                }

                foreach (['bootstrap', 'config', 'routes', 'tests', 'public'] as $child) {
                    mkdir($destination.'/'.$child, 0700, true);
                }

                foreach (['composer.json', '.env.example', 'artisan', 'bootstrap/app.php', 'config/app.php', 'routes/web.php', 'tests/TestCase.php', 'public/index.php'] as $relative) {
                    file_put_contents($destination.'/'.$relative, $relative === 'composer.json' ? 'fixture' : '<?php'."\n");
                }

                chmod($destination.'/artisan', 0755);

                return '';
            }

            if (($arguments[0] ?? null) === 'git' && ($arguments[4] ?? null) === 'HEAD') {
                return $pin['commit']."\n";
            }

            throw new RuntimeException('Unexpected injected process command.');
        };
        $clockCalls = 0;
        $clock = static function () use (&$clockCalls): string {
            $clockCalls++;

            return $clockCalls === 1 ? '2026-01-01T00:00:00+00:00' : '2026-02-01T00:00:00+00:00';
        };
        $builder = new SkeletonBuilder($this->tmpDir, $runner, null, $clock);

        self::assertSame(0, $builder->run(['10']));
        $manifestPath = $this->tmpDir.'/resources/skeletons/MANIFEST.json';
        $before = file_get_contents($manifestPath);
        self::assertIsString($before);
        $firstManifest = $this->decodeSingleManifest($before);
        self::assertSame(49, $firstManifest[10]['size']);
        self::assertSame(SkeletonBuilder::treeHash($this->tmpDir.'/resources/skeletons/10'), $firstManifest[10]['treeSha256']);
        self::assertTrue($firstManifest[10]['complete']);
        self::assertSame(0, $builder->run(['10']));
        self::assertSame($before, file_get_contents($manifestPath));
        self::assertSame(1, $clockCalls);
        self::assertSame(0755, fileperms($this->tmpDir.'/resources/skeletons/10/artisan') & 07777);
    }

    /** @return array{10: array{size: int, treeSha256: string, complete: bool}} */
    private function decodeSingleManifest(string $contents): array
    {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! array_key_exists(10, $decoded) || ! is_array($decoded[10])) {
            throw new RuntimeException('The fixture manifest has an invalid Laravel 10 entry.');
        }

        $entry = $decoded[10];
        $size = $entry['size'] ?? null;
        $treeSha256 = $entry['treeSha256'] ?? null;
        $complete = $entry['complete'] ?? null;

        if (! is_int($size) || ! is_string($treeSha256) || ! is_bool($complete)) {
            throw new RuntimeException('The fixture manifest has invalid entry fields.');
        }

        return [10 => ['size' => $size, 'treeSha256' => $treeSha256, 'complete' => $complete]];
    }

    private function validSnapshot(string $root, int $major, string $marker = 'snapshot'): string
    {
        $directory = $root.'/'.$major;

        foreach (['bootstrap', 'config', 'routes', 'tests', 'public'] as $child) {
            mkdir($directory.'/'.$child, 0700, true);
        }

        foreach (['composer.json', '.env.example', 'artisan', 'bootstrap/app.php', 'config/app.php', 'routes/web.php', 'tests/TestCase.php', 'public/index.php'] as $relative) {
            file_put_contents($directory.'/'.$relative, $relative === 'composer.json' ? $marker : '<?php'."\n");
        }

        return $directory;
    }

    /** @return array<string, string> */
    private function fileDigest(string $directory): array
    {
        $digest = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isFile() && ! $item->isLink()) {
                $path = $item->getPathname();
                $relative = str_replace('\\', '/', substr($path, strlen($directory) + 1));
                $hash = hash_file('sha256', $path);

                if ($hash !== false) {
                    $digest[$relative] = $hash;
                }
            }
        }

        ksort($digest);

        return $digest;
    }

    private function removeTree(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
