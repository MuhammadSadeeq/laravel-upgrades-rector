<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift;

use RuntimeException;

/** Uses checked-in snapshots as the source side of an explicitly offline run. */
final class OfflineSourceFetcher implements SourceFetcher
{
    private readonly string $root;

    public function __construct(string $root)
    {
        SafePath::assertNoSymlinkComponents(
            str_starts_with($root, '/') ? $root : getcwd().'/'.$root,
            'offline root',
        );
        $real = realpath($root);

        if ($real === false || ! is_dir($real) || is_link($root)) {
            throw new RuntimeException(sprintf('Offline root "%s" does not exist or is a symlink.', $root));
        }

        $this->root = $real;
    }

    public function fetch(string $url, int $maxBytes): string
    {
        if (preg_match('~/docs/(\d+)\.x/upgrade\.md$~', $url, $matches) === 1) {
            return $this->read('resources/guides/upgrade-'.$matches[1].'.md', $maxBytes);
        }

        if (str_contains($url, 'carbon.nesbot.com') || str_contains($url, 'Carbon')) {
            return $this->read('resources/guides/carbon-3.md', $maxBytes);
        }

        if (str_contains($url, 'laravel/laravel/tags') || str_contains($url, 'laravel/laravel/git/matching-refs')) {
            $manifest = $this->read('resources/skeletons/MANIFEST.json', $maxBytes);
            $decoded = json_decode($manifest, true);

            if (! is_array($decoded)) {
                throw new RuntimeException('The skeleton manifest is not a JSON object.');
            }

            $tags = [];

            foreach ($decoded as $major => $entry) {
                if (! is_numeric($major) || ! is_array($entry) || ! is_string($entry['tag'] ?? null)) {
                    continue;
                }

                $tags[] = ['name' => $entry['tag']];
            }

            if ($tags === []) {
                throw new RuntimeException('The skeleton manifest does not contain any tags.');
            }

            return json_encode($tags, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        throw new RuntimeException(sprintf('No offline mapping exists for source URL "%s".', $url));
    }

    private function read(string $relative, int $maxBytes): string
    {
        SafePath::assertInsideRoot($this->root, $relative, 'offline source');
        $path = $this->root.'/'.$relative;

        if (is_link($path) || ! is_file($path)) {
            throw new RuntimeException(sprintf('Offline source "%s" is missing or is not a regular file.', $relative));
        }

        $size = filesize($path);

        if ($size === false || $size > $maxBytes) {
            throw new RuntimeException(sprintf('Offline source "%s" exceeds the %d-byte limit.', $relative, $maxBytes));
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            throw new RuntimeException(sprintf('Offline source "%s" is empty or unreadable.', $relative));
        }

        return $contents;
    }
}
