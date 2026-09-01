<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\E2E;

use Closure;
use RuntimeException;

/**
 * Reserves a unique, not-yet-created application path for the E2E harness.
 *
 * The lock is created atomically and held until Composer has created the
 * application directory. This closes the small race between choosing a
 * random path and the create-project process claiming it.
 */
final class E2eApplicationDirectoryAllocator
{
    private const MAX_ATTEMPTS = 32;

    private readonly string $temporaryRoot;

    /** @var Closure(): string */
    private readonly Closure $suffixGenerator;

    /** @var array<string, string> */
    private array $locks = [];

    /** @param  (Closure(): string)|null  $suffixGenerator */
    public function __construct(
        string $temporaryRoot,
        ?Closure $suffixGenerator = null,
    ) {
        if (! $this->isAbsolutePath($temporaryRoot)) {
            throw new RuntimeException(sprintf(
                'The E2E temporary root must be an absolute path: %s.',
                $temporaryRoot,
            ));
        }

        $resolvedRoot = realpath($temporaryRoot);

        if (! is_string($resolvedRoot) || ! is_dir($resolvedRoot)) {
            throw new RuntimeException(sprintf(
                'The E2E temporary root must be an existing directory: %s.',
                $temporaryRoot,
            ));
        }

        if (! is_writable($resolvedRoot)) {
            throw new RuntimeException(sprintf(
                'The E2E temporary root must be writable: %s.',
                $resolvedRoot,
            ));
        }

        $this->temporaryRoot = $resolvedRoot;
        $this->suffixGenerator = $suffixGenerator ?? static fn (): string => bin2hex(random_bytes(16));
    }

    /**
     * Return a readable transition path which does not exist yet.
     *
     * @throws RuntimeException when no path can be reserved
     */
    public function allocate(int $from, int $to): string
    {
        $root = rtrim($this->temporaryRoot, '/\\');
        $prefix = sprintf('%s/e2e-%d-%d-', $root === '' ? DIRECTORY_SEPARATOR : $root, $from, $to);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $suffix = ($this->suffixGenerator)();

            if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/', $suffix) !== 1) {
                throw new RuntimeException('The E2E directory suffix generator returned an unsafe value.');
            }

            $directory = $prefix.$suffix;

            if ($this->pathExists($directory)) {
                continue;
            }

            $lock = $directory.'.lock';

            if ($this->pathExists($lock)) {
                continue;
            }

            $handle = @fopen($lock, 'x');

            if (! is_resource($handle)) {
                continue;
            }

            fclose($handle);

            // A concurrent creator may have claimed the application path
            // between the first check and our lock creation.
            if ($this->pathExists($directory)) {
                @unlink($lock);

                continue;
            }

            $this->locks[$directory] = $lock;

            return $directory;
        }

        throw new RuntimeException(sprintf(
            'Could not reserve a unique E2E application directory under %s.',
            $this->temporaryRoot,
        ));
    }

    /** Release the reservation after Composer has claimed the path. */
    public function release(string $directory): void
    {
        if (! isset($this->locks[$directory])) {
            return;
        }

        $lock = $this->locks[$directory];
        unset($this->locks[$directory]);

        if (is_file($lock) && ! @unlink($lock)) {
            throw new RuntimeException(sprintf('Could not release E2E directory reservation %s.', $lock));
        }
    }

    /** Release every reservation owned by this allocator instance. */
    public function releaseAll(): void
    {
        foreach (array_keys($this->locks) as $directory) {
            $this->release($directory);
        }
    }

    /** @phpstan-impure */
    private function pathExists(string $path): bool
    {
        return @lstat($path) !== false;
    }

    private function isAbsolutePath(string $path): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return preg_match('~\A(?:[A-Za-z]:[\\\\/]|[\\\\/]{2})~', $path) === 1;
        }

        return str_starts_with($path, '/');
    }
}
