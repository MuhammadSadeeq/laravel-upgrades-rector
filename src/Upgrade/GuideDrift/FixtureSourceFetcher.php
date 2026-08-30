<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift;

use RuntimeException;

/**
 * Reads the same logical sources from a local fixture directory.
 *
 * Candidate names intentionally cover both the documented layout and the
 * layout used by small CI fixtures. No recursive lookup is performed, which
 * keeps a fixture run deterministic and prevents path traversal.
 */
final class FixtureSourceFetcher implements SourceFetcher
{
    private readonly string $directory;

    public function __construct(string $directory)
    {
        SafePath::assertNoSymlinkComponents(
            str_starts_with($directory, '/') ? $directory : getcwd().'/'.$directory,
            'fixture directory',
        );
        $real = realpath($directory);

        if ($real === false || ! is_dir($real) || is_link($directory)) {
            throw new RuntimeException(sprintf('Fixture source directory "%s" does not exist or is a symlink.', $directory));
        }

        $this->directory = $real;
    }

    public function fetch(string $url, int $maxBytes): string
    {
        $candidates = $this->candidates($url);

        foreach ($candidates as $candidate) {
            $path = $this->directory.'/'.$candidate;

            SafePath::assertInsideRoot($this->directory, $candidate, 'fixture source');

            if (! file_exists($path)) {
                continue;
            }

            if (is_link($path) || ! is_file($path)) {
                throw new RuntimeException(sprintf('Fixture source "%s" is not a regular file.', $candidate));
            }

            $size = filesize($path);

            if ($size === false || $size > $maxBytes) {
                throw new RuntimeException(sprintf('Fixture source "%s" exceeds the %d-byte limit.', $candidate, $maxBytes));
            }

            $contents = file_get_contents($path);

            if ($contents === false || $contents === '') {
                throw new RuntimeException(sprintf('Fixture source "%s" is empty or unreadable.', $candidate));
            }

            return $contents;
        }

        throw new RuntimeException(sprintf(
            'Fixture source for "%s" is missing (expected one of: %s).',
            $url,
            implode(', ', $candidates),
        ));
    }

    /**
     * @return list<string>
     */
    private function candidates(string $url): array
    {
        if (preg_match('~/docs/(\d+)\.x/upgrade\.md$~', $url, $matches) === 1) {
            $major = $matches[1];

            return [
                'upgrade-'.$major.'.md',
                $major.'.x/upgrade.md',
                $major.'/upgrade.md',
            ];
        }

        if (str_contains($url, 'carbon.nesbot.com') || str_contains($url, 'Carbon')) {
            return ['carbon-3.md', 'carbon-migration.md', 'carbon-migration.html', 'carbon.html'];
        }

        if (str_contains($url, 'laravel/laravel/tags') || str_contains($url, 'laravel/laravel/git/matching-refs')) {
            return ['laravel-tags.json', 'tags.json'];
        }

        throw new RuntimeException(sprintf('No fixture mapping exists for source URL "%s".', $url));
    }
}
