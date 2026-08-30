<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\SafePath;
use RuntimeException;

/**
 * Local p2 source used by tests and by maintainers when reproducing a refresh
 * offline. The fixture directory mirrors the URL path where possible:
 * `vendor/name.json`, `p2/vendor/name.json`, or a flat `vendor-name.json`.
 */
final class FixturePackagistTransport
{
    private readonly string $directory;

    public function __construct(string $directory)
    {
        $workingDirectory = getcwd();

        if ($workingDirectory === false) {
            throw new RuntimeException('Could not resolve the working directory for the Packagist fixture.');
        }

        $absoluteDirectory = str_starts_with($directory, '/') ? $directory : $workingDirectory.'/'.$directory;
        SafePath::assertNoSymlinkComponents(
            $absoluteDirectory,
            'Packagist fixture directory',
        );
        $real = realpath($absoluteDirectory);

        if ($real === false || ! is_dir($real)) {
            throw new RuntimeException(sprintf('Packagist fixture directory "%s" does not exist or is a symlink.', $directory));
        }

        $this->directory = $real;
    }

    /**
     * @return array{status: int, body: string, headers: list<string>}
     */
    public function __invoke(string $url, int $maxBytes): array
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/p2/')) {
            throw new RuntimeException(sprintf('Fixture transport cannot map URL "%s".', $url));
        }

        $relative = ltrim(substr($path, 4), '/');
        $relative = rawurldecode($relative);

        $segments = explode('/', $relative);

        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '\\') || in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
            throw new RuntimeException(sprintf('Unsafe Packagist fixture path in URL "%s".', $url));
        }

        $name = preg_replace('/\.json$/', '', $relative);
        $name = is_string($name) ? $name : '';
        $flat = str_replace('/', '-', $name).'.json';
        $candidates = [
            $relative,
            'p2/'.$relative,
            $flat,
            $name.'.json',
        ];

        // Fixtures for paginated responses may use page-2.json or include a
        // query parameter in the URL returned by the first page.
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $page = $query['page'] ?? null;

        if (is_scalar($page) && ctype_digit((string) $page)) {
            $pageNumber = (string) $page;
            $candidates = array_merge(
                [$name.'-page-'.$pageNumber.'.json', 'p2/'.$name.'-page-'.$pageNumber.'.json'],
                $candidates,
            );
        }

        foreach ($candidates as $candidate) {
            SafePath::assertInsideRoot($this->directory, $candidate, 'Packagist fixture');
            $absolute = $this->directory.'/'.$candidate;

            if (! is_file($absolute)) {
                continue;
            }

            if (is_link($absolute)) {
                throw new RuntimeException(sprintf('Packagist fixture "%s" is a symlink.', $candidate));
            }

            $real = realpath($absolute);

            if ($real === false || ($real !== $this->directory && ! str_starts_with($real, $this->directory.'/'))) {
                throw new RuntimeException(sprintf('Packagist fixture "%s" escapes its root.', $candidate));
            }

            // Repeat the lexical and root checks immediately before reading;
            // this is a preflight policy and cannot make a concurrent swap
            // race-proof on portable PHP.
            SafePath::assertInsideRoot($this->directory, $candidate, 'Packagist fixture');
            SafePath::assertNoSymlinkComponents($absolute, 'Packagist fixture');

            $size = filesize($absolute);

            if ($size === false || $size > $maxBytes) {
                throw new RuntimeException(sprintf('Packagist fixture "%s" exceeds the %d-byte limit.', $candidate, $maxBytes));
            }

            $body = file_get_contents($absolute);

            if ($body === false || $body === '') {
                throw new RuntimeException(sprintf('Packagist fixture "%s" is empty or unreadable.', $candidate));
            }

            return ['status' => 200, 'body' => $body, 'headers' => []];
        }

        throw new RuntimeException(sprintf('No Packagist fixture exists for "%s".', $url));
    }
}
