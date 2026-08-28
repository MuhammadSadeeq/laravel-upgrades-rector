<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Builds and verifies the checked-in Laravel skeleton snapshots.
 *
 * The builder deliberately stages every requested major before replacing any
 * checked-in tree. Snapshot replacement and the manifest update are then
 * committed as one filesystem transaction with rollback on failure.
 */
final class SkeletonBuilder
{
    public const SOURCE_URL = 'https://github.com/laravel/laravel.git';

    /** @var array<int, array{tag: string, commit: string}> */
    public const PINNED_SNAPSHOTS = [
        10 => ['tag' => 'v10.3.3', 'commit' => 'd3287461e15862d1c7a8f10925988b4f1640d92b'],
        11 => ['tag' => 'v11.6.1', 'commit' => 'e417ebc95d76da3cbee761f0d2b77aebdf52cdc9'],
        12 => ['tag' => 'v12.12.2', 'commit' => '945f4e5a9fd3695dc0ee512f497c650fb82cfbb8'],
        13 => ['tag' => 'v13.10.1', 'commit' => '5aad4ddf34d5e21dfe6b4c07eeac67d5bd5e08b0'],
    ];

    /** @var Closure(list<string>, int): string */
    private readonly Closure $processRunner;

    /** @var Closure(string, string): bool */
    private readonly Closure $rename;

    /** @var Closure(): string */
    private readonly Closure $clock;

    /**
     * @param  Closure(list<string>, int): string|null  $processRunner
     * @param  Closure(string, string): bool|null  $rename
     * @param  Closure(): string|null  $clock
     */
    public function __construct(
        private readonly string $root,
        ?Closure $processRunner = null,
        ?Closure $rename = null,
        ?Closure $clock = null,
    ) {
        $this->processRunner = $processRunner ?? static function (array $arguments, int $timeout): string {
            $process = new Process($arguments);
            $process->setTimeout($timeout);
            $process->mustRun();

            return $process->getOutput();
        };
        $this->rename = $rename ?? static fn (string $from, string $to): bool => rename($from, $to);
        $this->clock = $clock ?? static fn (): string => gmdate(DATE_ATOM);
    }

    /**
     * @param  list<string>  $arguments
     */
    public function run(array $arguments): int
    {
        try {
            [$check, $remote, $majors] = self::parseArguments($arguments);
            $snapshotRoot = $this->safeSnapshotRoot();

            if ($check) {
                if (! is_dir($snapshotRoot)) {
                    throw new RuntimeException(sprintf('Snapshot directory "%s" does not exist.', $snapshotRoot));
                }

                $this->verifySnapshots($snapshotRoot, $majors, $remote);
                fwrite(STDOUT, sprintf("Skeleton check passed for Laravel %s.\n", implode(', ', $majors)));

                return 0;
            }

            if (! is_dir($snapshotRoot) && ! mkdir($snapshotRoot, 0777, true) && ! is_dir($snapshotRoot)) {
                throw new RuntimeException(sprintf('Could not create snapshot directory "%s".', $snapshotRoot));
            }

            /** @var array<string, mixed> $manifest */
            $manifest = $this->buildManifest($snapshotRoot);
            $temporaryRoot = $this->makeTemporaryRoot($snapshotRoot);

            try {
                $staged = [];

                // Fetch, copy, validate and hash everything before touching the
                // checked-in snapshots or manifest.
                foreach ($majors as $major) {
                    $pin = self::PINNED_SNAPSHOTS[$major];
                    $cloneDirectory = $temporaryRoot.'/clone-'.$major;
                    $stageDirectory = $temporaryRoot.'/stage-'.$major;

                    $this->fetchSnapshot($cloneDirectory, $pin['tag'], $pin['commit']);
                    $this->copySnapshot($cloneDirectory, $stageDirectory);
                    $this->validateSnapshot($stageDirectory, $major);

                    $size = self::directorySize($stageDirectory);
                    $treeHash = self::treeHash($stageDirectory);
                    $old = $manifest[(string) $major] ?? null;
                    $fetchedAt = is_array($old)
                        && ($old['tag'] ?? null) === $pin['tag']
                        && ($old['commit'] ?? null) === $pin['commit']
                        && ($old['complete'] ?? null) === true
                        && ($old['size'] ?? null) === $size
                        && ($old['treeSha256'] ?? null) === $treeHash
                        && is_string($old['fetchedAt'] ?? null)
                        ? $old['fetchedAt']
                        : ($this->clock)();

                    $manifest[(string) $major] = [
                        'tag' => $pin['tag'],
                        'commit' => $pin['commit'],
                        'fetchedAt' => $fetchedAt,
                        'complete' => true,
                        'size' => $size,
                        'treeSha256' => $treeHash,
                        'excluded' => ['.git', 'README.md', 'node_modules'],
                    ];
                    $staged[$major] = $stageDirectory;

                    fwrite(STDOUT, sprintf(
                        "Fetched Laravel %d %s (%d bytes).\n",
                        $major,
                        $pin['tag'],
                        $size,
                    ));
                }

                $this->installTransaction($snapshotRoot, $staged, $manifest, $temporaryRoot);
            } finally {
                $this->removeTree($temporaryRoot, dirname($temporaryRoot));
            }

            return 0;
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception->getMessage()."\n");

            return 1;
        }
    }

    /**
     * @param  list<string>  $arguments
     * @return array{0: bool, 1: bool, 2: list<int>}
     */
    public static function parseArguments(array $arguments): array
    {
        $check = false;
        $remote = false;
        $majors = [];

        foreach ($arguments as $argument) {
            if ($argument === '--check') {
                $check = true;

                continue;
            }

            if ($argument === '--remote') {
                $remote = true;

                continue;
            }

            if (preg_match('/^(10|11|12|13)$/', $argument) !== 1) {
                throw new RuntimeException(sprintf(
                    'Unknown argument "%s". Use [10|11|12|13] and optionally --check [--remote].',
                    $argument,
                ));
            }

            $major = (int) $argument;

            if (in_array($major, $majors, true)) {
                throw new RuntimeException(sprintf('Laravel %d was specified more than once.', $major));
            }

            $majors[] = $major;
        }

        if ($remote && ! $check) {
            throw new RuntimeException('--remote is only valid with --check.');
        }

        if ($majors === []) {
            $majors = array_keys(self::PINNED_SNAPSHOTS);
        }

        sort($majors);

        return [$check, $remote, $majors];
    }

    public static function remoteRefMatches(string $output, string $tag, string $commit): bool
    {
        $expected = $commit."\trefs/tags/".$tag;

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (trim($line) === $expected) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public static function manifestEntry(string $tag, string $commit, string $fetchedAt, int $size, string $treeHash): array
    {
        return [
            'tag' => $tag,
            'commit' => $commit,
            'fetchedAt' => $fetchedAt,
            'complete' => true,
            'size' => $size,
            'treeSha256' => $treeHash,
            'excluded' => ['.git', 'README.md', 'node_modules'],
        ];
    }

    public static function directorySize(string $directory): int
    {
        $size = 0;

        foreach (self::files($directory) as [$relative, $path, $mode]) {
            unset($mode);

            if (! self::excluded($relative)) {
                $size += filesize($path) ?: 0;
            }
        }

        return $size;
    }

    public static function treeHash(string $directory): string
    {
        $hash = hash_init('sha256');
        $files = [];

        foreach (self::files($directory) as [$relative, $path, $mode]) {
            if (! self::excluded($relative)) {
                $files[] = [$relative, $path, $mode];
            }
        }

        usort($files, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        foreach ($files as [$relative, $path, $mode]) {
            hash_update($hash, $relative."\0".sprintf('%04o', $mode)."\0");
            $handle = fopen($path, 'rb');

            if ($handle === false) {
                throw new RuntimeException(sprintf('Could not read snapshot file "%s".', $relative));
            }

            try {
                while (! feof($handle)) {
                    $chunk = fread($handle, 1024 * 1024);

                    if ($chunk === false) {
                        throw new RuntimeException(sprintf('Could not hash snapshot file "%s".', $relative));
                    }

                    hash_update($hash, $chunk);
                }
            } finally {
                fclose($handle);
            }
        }

        return hash_final($hash);
    }

    public static function excluded(string $relative): bool
    {
        return $relative === '.git'
            || str_starts_with($relative, '.git/')
            || $relative === 'README.md'
            || str_starts_with($relative, 'node_modules/');
    }

    public function validateSnapshot(string $directory, int $major): void
    {
        if (! is_dir($directory) || is_link($directory)) {
            throw new RuntimeException(sprintf('Laravel %d snapshot directory "%s" does not exist or is a symlink.', $major, $directory));
        }

        $this->assertNoSymlinks($directory);

        foreach (['composer.json', '.env.example', 'artisan', 'bootstrap/app.php', 'config/app.php', 'routes/web.php', 'tests/TestCase.php', 'public/index.php'] as $relative) {
            if (! is_file($directory.'/'.$relative)) {
                throw new RuntimeException(sprintf('Laravel %d snapshot is missing expected file "%s".', $major, $relative));
            }
        }

        foreach (['.git', 'README.md', 'node_modules'] as $relative) {
            if (file_exists($directory.'/'.$relative) || is_link($directory.'/'.$relative)) {
                throw new RuntimeException(sprintf('Laravel %d snapshot contains excluded path "%s".', $major, $relative));
            }
        }

        $size = self::directorySize($directory);

        if ($size >= 1024 * 1024) {
            throw new RuntimeException(sprintf('Laravel %d snapshot is %d bytes; expected less than 1 MB.', $major, $size));
        }
    }

    public function copySnapshot(string $source, string $destination): void
    {
        if (! is_dir($source) || is_link($source)) {
            throw new RuntimeException(sprintf('Fetched snapshot "%s" does not exist or is a symlink.', $source));
        }

        if (! mkdir($destination, 0777, true) && ! is_dir($destination)) {
            throw new RuntimeException(sprintf('Could not create staging directory "%s".', $destination));
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));

            if (self::excluded($relative)) {
                continue;
            }

            if ($item->isLink()) {
                throw new RuntimeException(sprintf('Refusing symlink in fetched snapshot: "%s".', $relative));
            }

            if (! $item->isFile()) {
                continue;
            }

            $files[] = [$relative, $item->getPathname(), $item->getPerms() & 07777];
        }

        usort($files, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        foreach ($files as [$relative, $path, $mode]) {
            $target = $destination.'/'.$relative;
            $parent = dirname($target);

            if (! is_dir($parent) && ! mkdir($parent, 0777, true) && ! is_dir($parent)) {
                throw new RuntimeException(sprintf('Could not create snapshot parent "%s".', $parent));
            }

            if (! copy($path, $target) || ! chmod($target, $mode)) {
                throw new RuntimeException(sprintf('Could not copy snapshot file "%s".', $relative));
            }
        }
    }

    /**
     * Install staged directories and the manifest as one rollback-capable
     * transaction. This is public for focused filesystem-transaction tests.
     *
     * @param  array<int, string>  $staged
     * @param  array<int|string, mixed>  $manifest
     */
    public function installTransaction(string $snapshotRoot, array $staged, array $manifest, string $temporaryRoot): void
    {
        $this->assertSafeProjectPath($snapshotRoot);
        $this->assertSafeProjectPath($temporaryRoot);

        $backupRoot = $temporaryRoot.'/backups';

        if (! mkdir($backupRoot, 0700, true) && ! is_dir($backupRoot)) {
            throw new RuntimeException(sprintf('Could not create snapshot backup directory "%s".', $backupRoot));
        }

        $backups = [];
        $installed = [];
        $manifestPath = $snapshotRoot.'/MANIFEST.json';
        $manifestBackup = $backupRoot.'/MANIFEST.json';
        $manifestMoved = false;
        $manifestWritten = false;

        try {
            foreach ($staged as $major => $stage) {
                $this->assertSafeProjectPath($stage);
                $destination = $snapshotRoot.'/'.$major;
                $this->assertSafeSnapshotPath($snapshotRoot, $destination, $major);

                if (is_link($destination)) {
                    throw new RuntimeException(sprintf('Refusing to replace symlink snapshot path "%s".', $destination));
                }

                if (file_exists($destination)) {
                    if (! is_dir($destination)) {
                        throw new RuntimeException(sprintf('Snapshot destination "%s" is not a directory.', $destination));
                    }

                    $this->assertNoSymlinks($destination);
                    $backup = $backupRoot.'/'.$major;

                    if (! ($this->rename)($destination, $backup)) {
                        throw new RuntimeException(sprintf('Could not protect snapshot "%s" before replacement.', $destination));
                    }

                    $backups[$major] = $backup;
                }

                if (! ($this->rename)($stage, $destination)) {
                    throw new RuntimeException(sprintf('Could not install Laravel %d snapshot.', $major));
                }

                $installed[] = $major;
            }

            if (is_link($manifestPath)) {
                throw new RuntimeException(sprintf('Refusing to replace symlink manifest "%s".', $manifestPath));
            }

            if (is_file($manifestPath) && ! ($this->rename)($manifestPath, $manifestBackup)) {
                throw new RuntimeException('Could not protect the existing skeleton manifest before replacement.');
            }

            $manifestMoved = is_file($manifestBackup);
            /** @var array<string, mixed> $normalizedManifest */
            $normalizedManifest = [];

            foreach ($manifest as $key => $value) {
                $normalizedManifest[(string) $key] = $value;
            }

            $this->writeManifest($manifestPath, $normalizedManifest);
            $manifestWritten = true;

            foreach ($backups as $backup) {
                $this->removeTree($backup, $backupRoot);
            }

            // Do not discard the old manifest until every old snapshot backup
            // has been cleaned successfully. If cleanup throws, the catch
            // block can still restore both the manifest and snapshots.
            if ($manifestMoved && is_file($manifestBackup)) {
                unlink($manifestBackup);
            }
        } catch (Throwable $exception) {
            if ($manifestWritten && is_file($manifestPath)) {
                unlink($manifestPath);
            }

            if ($manifestMoved && is_file($manifestBackup)) {
                ($this->rename)($manifestBackup, $manifestPath);
            }

            foreach (array_reverse($installed) as $major) {
                $destination = $snapshotRoot.'/'.$major;

                if (is_dir($destination) || is_link($destination)) {
                    $this->removeTree($destination, $snapshotRoot);
                }
            }

            foreach (array_reverse($backups, true) as $major => $backup) {
                if (is_dir($backup)) {
                    ($this->rename)($backup, $snapshotRoot.'/'.$major);
                }
            }

            throw $exception;
        }
    }

    /** @param list<int> $majors */
    private function verifySnapshots(string $snapshotRoot, array $majors, bool $remote): void
    {
        $manifestPath = $snapshotRoot.'/MANIFEST.json';

        if (! is_file($manifestPath) || is_link($manifestPath)) {
            throw new RuntimeException(sprintf('Manifest "%s" does not exist.', $manifestPath));
        }

        $contents = file_get_contents($manifestPath);
        $manifest = is_string($contents) ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR) : null;

        if (! is_array($manifest) || ($manifest['schemaVersion'] ?? null) !== 1 || ($manifest['source'] ?? null) !== 'laravel/laravel') {
            throw new RuntimeException('Manifest must have schemaVersion 1 and source laravel/laravel.');
        }

        foreach ($majors as $major) {
            $pin = self::PINNED_SNAPSHOTS[$major];
            $entry = $manifest[(string) $major] ?? null;

            if (! is_array($entry)
                || ($entry['complete'] ?? null) !== true
                || ($entry['tag'] ?? null) !== $pin['tag']
                || ($entry['commit'] ?? null) !== $pin['commit']) {
                throw new RuntimeException(sprintf('Manifest provenance for Laravel %d is missing or not pinned.', $major));
            }

            $directory = $snapshotRoot.'/'.$major;
            $this->validateSnapshot($directory, $major);

            if (($entry['size'] ?? null) !== self::directorySize($directory)) {
                throw new RuntimeException(sprintf('Laravel %d snapshot size differs from its manifest.', $major));
            }

            if (($entry['treeSha256'] ?? null) !== self::treeHash($directory)) {
                throw new RuntimeException(sprintf('Laravel %d snapshot content differs from its manifest.', $major));
            }

            if ($remote) {
                $output = ($this->processRunner)(['git', 'ls-remote', '--tags', '--refs', self::SOURCE_URL, $pin['tag']], 120);

                if (! self::remoteRefMatches($output, $pin['tag'], $pin['commit'])) {
                    throw new RuntimeException(sprintf('Remote provenance for %s no longer matches %s.', $pin['tag'], $pin['commit']));
                }
            }
        }
    }

    private function fetchSnapshot(string $destination, string $tag, string $commit): void
    {
        ($this->processRunner)([
            'git',
            'clone',
            '--depth',
            '1',
            '--branch',
            $tag,
            '--single-branch',
            self::SOURCE_URL,
            $destination,
        ], 300);

        $actualCommit = trim(($this->processRunner)(['git', '-C', $destination, 'rev-parse', 'HEAD'], 30));

        if (! hash_equals($commit, $actualCommit)) {
            throw new RuntimeException(sprintf(
                'Pinned Laravel tag %s resolved to %s, expected %s.',
                $tag,
                $actualCommit,
                $commit,
            ));
        }
    }

    /** @return array<string, mixed> */
    private function buildManifest(string $snapshotRoot): array
    {
        $path = $snapshotRoot.'/MANIFEST.json';

        if (! is_file($path) || is_link($path)) {
            return ['schemaVersion' => 1, 'source' => 'laravel/laravel'];
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw new RuntimeException(sprintf('Manifest "%s" is empty.', $path));
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('Manifest "%s" must contain an object.', $path));
        }

        // Keep only supported-major entries. Legacy generatedAt/note fields
        // are intentionally removed; fetchedAt is the sole time-varying field.
        $manifest = ['schemaVersion' => 1, 'source' => 'laravel/laravel'];

        foreach ($decoded as $key => $value) {
            $keyString = (string) $key;

            if (preg_match('/^(10|11|12|13)$/', $keyString) === 1 && is_array($value)) {
                $manifest[$keyString] = $value;
            }
        }

        return $manifest;
    }

    private function makeTemporaryRoot(string $snapshotRoot): string
    {
        $parent = dirname($snapshotRoot);
        $parentReal = realpath($parent);

        if ($parentReal === false) {
            throw new RuntimeException(sprintf('Could not resolve snapshot parent "%s".', $parent));
        }

        $directory = $parent.'/.laravel-upgrade-skeleton-'.bin2hex(random_bytes(8));

        if (file_exists($directory) || is_link($directory)) {
            throw new RuntimeException(sprintf('Temporary snapshot directory already exists: "%s".', $directory));
        }

        if (! mkdir($directory, 0700, true)) {
            throw new RuntimeException(sprintf('Could not create temporary directory "%s".', $directory));
        }

        $directoryReal = realpath($directory);

        if ($directoryReal === false || dirname($directoryReal) !== $parentReal) {
            $this->removeTree($directory, $parent);
            throw new RuntimeException(sprintf('Temporary snapshot directory "%s" is not on the snapshot filesystem.', $directory));
        }

        return $directory;
    }

    private function safeSnapshotRoot(): string
    {
        $projectRoot = $this->projectRoot();
        $snapshotRoot = $projectRoot.'/resources/skeletons';
        $this->assertSafeProjectPath($snapshotRoot);

        return $snapshotRoot;
    }

    private function projectRoot(): string
    {
        if (is_link($this->root)) {
            throw new RuntimeException(sprintf('Refusing symlink project root "%s".', $this->root));
        }

        $projectRoot = realpath($this->root);

        if ($projectRoot === false || ! is_dir($projectRoot)) {
            throw new RuntimeException(sprintf('Configured project root "%s" does not exist.', $this->root));
        }

        return rtrim($projectRoot, '/');
    }

    /**
     * Reject symlinks in every component below the configured project root and
     * reject paths whose resolved target escapes that root. Missing final
     * components are allowed for the build path, but their existing parents
     * are still checked.
     */
    private function assertSafeProjectPath(string $path): void
    {
        $projectRoot = $this->projectRoot();
        $configuredRoot = rtrim($this->root, '/');
        $candidate = $projectRoot.'/'.$path;

        if (str_starts_with($path, '/')) {
            // Tests and callers may use a lexical alias such as /var on
            // macOS, while realpath($root) resolves it to /private/var.
            // Canonicalize that alias without following any component below
            // the configured root; those components are checked below.
            $candidate = str_starts_with($path, $configuredRoot.'/')
                ? $projectRoot.substr($path, strlen($configuredRoot))
                : $path;
        }

        if ($candidate !== $projectRoot && ! str_starts_with($candidate, $projectRoot.'/')) {
            throw new RuntimeException(sprintf('Refusing path outside project root: "%s".', $path));
        }

        $relative = $candidate === $projectRoot ? '' : substr($candidate, strlen($projectRoot) + 1);
        $current = $projectRoot;

        foreach (explode('/', $relative) as $component) {
            if ($component === '' || $component === '.') {
                continue;
            }

            if ($component === '..') {
                throw new RuntimeException(sprintf('Refusing traversal path: "%s".', $path));
            }

            $current .= '/'.$component;

            if (is_link($current)) {
                throw new RuntimeException(sprintf('Refusing symlink path component "%s".', $current));
            }
        }

        $resolved = realpath($candidate);

        if ($resolved !== false && $resolved !== $projectRoot && ! str_starts_with($resolved, $projectRoot.'/')) {
            throw new RuntimeException(sprintf('Resolved path escapes project root: "%s".', $path));
        }
    }

    /** @param array<string, mixed> $manifest */
    private function writeManifest(string $path, array $manifest): void
    {
        ksort($manifest, SORT_NATURAL);

        try {
            $contents = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        } catch (\JsonException $exception) {
            throw new RuntimeException('Could not encode the skeleton manifest.', 0, $exception);
        }

        $temporary = tempnam(dirname($path), '.skeleton-manifest-');

        if ($temporary === false) {
            throw new RuntimeException('Could not create a temporary skeleton manifest.');
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || ! ($this->rename)($temporary, $path)) {
                throw new RuntimeException(sprintf('Could not atomically write manifest "%s".', $path));
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function assertSafeSnapshotPath(string $snapshotRoot, string $path, int $major): void
    {
        $root = realpath($snapshotRoot);
        $parent = realpath(dirname($path));

        if ($root === false || $parent === false || $parent !== $root || basename($path) !== (string) $major) {
            throw new RuntimeException(sprintf('Refusing unsafe snapshot path "%s".', $path));
        }
    }

    private function assertNoSymlinks(string $directory): void
    {
        if (is_link($directory)) {
            throw new RuntimeException(sprintf('Refusing symlink tree "%s".', $directory));
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $item) {
            /** @var SplFileInfo $item */
            if ($item->isLink()) {
                throw new RuntimeException(sprintf('Refusing symlink in snapshot tree "%s".', $item->getPathname()));
            }
        }
    }

    /** @return list<array{0: string, 1: string, 2: int}> */
    private static function files(string $directory): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $item) {
            /** @var SplFileInfo $item */
            if (! $item->isFile() || $item->isLink()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($directory) + 1));
            $files[] = [$relative, $item->getPathname(), $item->getPerms() & 07777];
        }

        usort($files, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        return $files;
    }

    private function removeTree(string $directory, string $allowedParent): void
    {
        if (is_link($directory)) {
            $parent = realpath(dirname($directory));
            $allowed = realpath($allowedParent);

            if ($parent === false || $allowed === false || $parent !== $allowed) {
                throw new RuntimeException(sprintf('Refusing unsafe cleanup path "%s".', $directory));
            }

            unlink($directory);

            return;
        }

        $parent = realpath(dirname($directory));
        $allowed = realpath($allowedParent);

        if ($parent === false || $allowed === false || $parent !== $allowed || ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Refusing unsafe cleanup path "%s".', $directory));
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            /** @var SplFileInfo $item */
            $path = $item->getPathname();

            if ($item->isLink()) {
                unlink($path);
            } elseif ($item->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
