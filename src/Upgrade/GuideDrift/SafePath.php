<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift;

use RuntimeException;

/** Small path policy shared by source readers and report/snapshot writers. */
final class SafePath
{
    /**
     * macOS exposes these temporary/system locations through harmless aliases
     * (/var -> /private/var and /tmp -> /private/tmp). They are allowed while
     * user-created symlink components remain rejected.
     *
     * @var list<string>
     */
    private const SYSTEM_ALIASES = ['/tmp', '/var'];

    private function __construct() {}

    /**
     * Assert that a repository-relative path never traverses a symlink and,
     * when it exists, resolves below the declared root.
     */
    public static function assertInsideRoot(string $root, string $relative, string $description): void
    {
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, "\0")) {
            throw new RuntimeException(sprintf('Unsafe %s path "%s".', $description, $relative));
        }

        $current = rtrim($root, '/');

        foreach (explode('/', $relative) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                throw new RuntimeException(sprintf('Unsafe %s path "%s".', $description, $relative));
            }

            $current .= '/'.$part;

            if (is_link($current)) {
                throw new RuntimeException(sprintf('Refusing %s through symlink path "%s".', $description, $relative));
            }
        }

        $resolved = realpath($current);

        if ($resolved === false) {
            return;
        }

        $resolvedRoot = realpath($root);

        if ($resolvedRoot === false || ($resolved !== $resolvedRoot && ! str_starts_with($resolved, rtrim($resolvedRoot, '/').'/'))) {
            throw new RuntimeException(sprintf('Resolved %s path "%s" escapes its root.', $description, $relative));
        }
    }

    /**
     * Assert all existing components of an absolute path are regular path
     * components. Missing leaf/parent components are allowed so callers can
     * create them after this preflight; any existing symlink is rejected.
     */
    public static function assertNoSymlinkComponents(string $absolute, string $description): void
    {
        if (! str_starts_with($absolute, '/') || str_contains($absolute, "\0")) {
            throw new RuntimeException(sprintf('Unsafe %s path "%s".', $description, $absolute));
        }

        $current = '';

        foreach (explode('/', trim($absolute, '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                throw new RuntimeException(sprintf('Unsafe %s path "%s".', $description, $absolute));
            }

            $current .= '/'.$part;

            if (! is_link($current)) {
                continue;
            }

            if (in_array($current, self::SYSTEM_ALIASES, true)) {
                continue;
            }

            throw new RuntimeException(sprintf('Refusing %s through symlink path "%s".', $description, $absolute));
        }
    }
}
