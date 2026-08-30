<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift;

use RuntimeException;
use Throwable;

/**
 * Compares upstream Laravel/Carbon headings and skeleton tags with vendored
 * maintainer snapshots. Guide prose is deliberately not compared: upstream
 * wording and links change frequently, while heading additions/removals are a
 * useful signal for a maintainer and keep the weekly issue actionable.
 */
final class GuideDriftChecker
{
    public const CARBON_URL = 'https://carbon.nesbot.com/guide/getting-started/migration.html';

    /**
     * GitHub's matching-refs endpoint returns all refs with the given prefix.
     * The checker appends a major (for example, `10`) to this URL and fetches
     * one bounded response per watched major (for example, `v10.`). A single `tags?per_page=100`
     * response is not sufficient because old Laravel majors eventually fall
     * past the first page.
     */
    public const TAGS_URL = 'https://api.github.com/repos/laravel/laravel/git/matching-refs/tags/v';

    private const MAX_GUIDE_BYTES = 2_097_152;

    private const MAX_TAGS_BYTES = 524_288;

    private const MAX_MANIFEST_BYTES = 524_288;

    /** @var list<int> */
    private const WATCHED_MANIFEST_MAJORS = [10, 11, 12, 13];

    private const NEXT_MAJOR = 14;

    /**
     * @var array<string, array{url: string, snapshot: string, maxBytes: int}>
     */
    private const GUIDE_SOURCES = [
        'laravel-11' => [
            'url' => 'https://raw.githubusercontent.com/laravel/docs/11.x/upgrade.md',
            'snapshot' => 'resources/guides/upgrade-11.md',
            'maxBytes' => self::MAX_GUIDE_BYTES,
        ],
        'laravel-12' => [
            'url' => 'https://raw.githubusercontent.com/laravel/docs/12.x/upgrade.md',
            'snapshot' => 'resources/guides/upgrade-12.md',
            'maxBytes' => self::MAX_GUIDE_BYTES,
        ],
        'laravel-13' => [
            'url' => 'https://raw.githubusercontent.com/laravel/docs/13.x/upgrade.md',
            'snapshot' => 'resources/guides/upgrade-13.md',
            'maxBytes' => self::MAX_GUIDE_BYTES,
        ],
        'carbon-3' => [
            // This is Carbon's canonical migration page. It is intentionally
            // used instead of a generated mirror so the report links to the
            // source maintainers should review before refreshing snapshots.
            'url' => self::CARBON_URL,
            'snapshot' => 'resources/guides/carbon-3.md',
            'maxBytes' => self::MAX_GUIDE_BYTES,
        ],
    ];

    private readonly string $root;

    public function __construct(
        string $root,
        private readonly SourceFetcher $fetcher,
    ) {
        $real = realpath($root);

        if ($real === false || ! is_dir($real) || is_link($root)) {
            throw new RuntimeException(sprintf('Guide-drift root "%s" does not exist or is a symlink.', $root));
        }

        $this->root = $real;
    }

    /**
     * @return array<string, mixed>
     */
    public function run(bool $refresh = false): array
    {
        try {
            return $this->runChecked($refresh);
        } catch (Throwable $exception) {
            return [
                'schemaVersion' => 1,
                'status' => 'error',
                'sources' => $this->sourceReport(),
                'drift' => [],
                'errors' => [$exception->getMessage()],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function runChecked(bool $refresh): array
    {
        $manifest = $this->readManifest();
        $sourceContents = [];

        foreach (self::GUIDE_SOURCES as $key => $source) {
            $sourceContents[$key] = $this->fetcher->fetch($source['url'], $source['maxBytes']);

            if (HeadingExtractor::extract($sourceContents[$key]) === []) {
                throw new RuntimeException(sprintf('Source "%s" contains no headings.', $source['url']));
            }
        }

        $tags = $this->fetchTags();
        $drift = [];

        foreach (self::GUIDE_SOURCES as $key => $source) {
            $snapshot = $refresh
                ? $sourceContents[$key]
                : $this->readFile($source['snapshot'], self::MAX_GUIDE_BYTES);
            $currentHeadings = HeadingExtractor::extract($sourceContents[$key]);
            $snapshotHeadings = HeadingExtractor::extract($snapshot);
            $added = $this->headingDifference($currentHeadings, $snapshotHeadings);
            $removed = $this->headingDifference($snapshotHeadings, $currentHeadings);

            if ($added !== [] || $removed !== []) {
                $drift[] = [
                    'type' => 'guide-headings',
                    'target' => $key,
                    'url' => $source['url'],
                    'snapshot' => $source['snapshot'],
                    'added' => array_map([self::class, 'displayHeading'], $added),
                    'removed' => array_map([self::class, 'displayHeading'], $removed),
                ];
            }
        }

        $drift = array_merge($drift, $this->tagDrift($manifest, $tags));

        if ($refresh) {
            $this->writeSnapshots($sourceContents);
        }

        usort($drift, static function (array $left, array $right): int {
            $leftKey = self::sortPart($left, 'type').'|'.self::sortPart($left, 'target').'|'.self::sortPart($left, 'major');
            $rightKey = self::sortPart($right, 'type').'|'.self::sortPart($right, 'target').'|'.self::sortPart($right, 'major');

            return $leftKey <=> $rightKey;
        });

        return [
            'schemaVersion' => 1,
            'status' => $drift === [] ? 'clean' : 'drift',
            'sources' => $this->sourceReport(),
            'drift' => $drift,
            'errors' => [],
            ...($refresh ? ['refreshed' => true] : []),
        ];
    }

    /**
     * @return array<string, array{url: string, snapshot: string}>
     */
    private function sourceReport(): array
    {
        $sources = [];

        foreach (self::GUIDE_SOURCES as $key => $source) {
            $sources[$key] = [
                'url' => $source['url'],
                'snapshot' => $source['snapshot'],
            ];
        }

        foreach ([...self::WATCHED_MANIFEST_MAJORS, self::NEXT_MAJOR] as $major) {
            $sources['laravel-tags-'.$major] = [
                'url' => self::tagsUrl($major),
                'snapshot' => 'resources/skeletons/MANIFEST.json',
            ];
        }

        return $sources;
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(): array
    {
        $contents = $this->readFile('resources/skeletons/MANIFEST.json', self::MAX_MANIFEST_BYTES);
        $manifest = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);

        if (! is_array($manifest)) {
            throw new RuntimeException('The skeleton manifest must be a JSON object.');
        }

        foreach (self::WATCHED_MANIFEST_MAJORS as $major) {
            $entry = $manifest[(string) $major] ?? null;
            $tag = is_array($entry) ? ($entry['tag'] ?? null) : null;
            $version = is_string($tag) ? self::parseVersion($tag) : null;

            if (! is_array($entry) || ! is_string($tag) || $version === null || $version['major'] !== $major) {
                throw new RuntimeException(sprintf('The skeleton manifest has no valid Laravel %d tag.', $major));
            }
        }

        $normalized = [];

        foreach ($manifest as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * @return list<array{name: string, version: array{major: int, minor: int, patch: int, pre: list<string>}}>
     */
    private function parseTags(string $contents, bool $allowEmpty = false): array
    {
        try {
            $decoded = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('The Laravel tags source is malformed JSON.', 0, $exception);
        }

        $entries = is_array($decoded) && array_is_list($decoded)
            ? $decoded
            : (is_array($decoded) && is_array($decoded['tags'] ?? null) ? $decoded['tags'] : null);

        if ($entries === null || ($entries === [] && ! $allowEmpty)) {
            throw new RuntimeException('The Laravel tags source must contain a non-empty list.');
        }

        if ($entries === []) {
            return [];
        }

        $tags = [];

        foreach ($entries as $entry) {
            $name = is_string($entry)
                ? $entry
                : (is_array($entry) && is_string($entry['name'] ?? null)
                    ? $entry['name']
                    : (is_array($entry) && is_string($entry['ref'] ?? null)
                        ? $this->tagNameFromRef($entry['ref'])
                        : null));
            $version = is_string($name) ? self::parseVersion($name) : null;

            if ($name === null || $version === null) {
                continue;
            }

            $name = trim($name);

            $tags[] = ['name' => $name, 'version' => $version];
        }

        if ($tags === []) {
            throw new RuntimeException('The Laravel tags source contains no valid semantic-version tags.');
        }

        return $tags;
    }

    /**
     * Fetch one bounded matching-ref response for every manifest major and the
     * next major. The latter is allowed to be an empty JSON list until Laravel
     * publishes that major, while a missing/malformed response remains an
     * operational error.
     *
     * @return list<array{name: string, version: array{major: int, minor: int, patch: int, pre: list<string>}}>
     */
    private function fetchTags(): array
    {
        $tags = [];

        foreach ([...self::WATCHED_MANIFEST_MAJORS, self::NEXT_MAJOR] as $major) {
            $contents = $this->fetcher->fetch(self::tagsUrl($major), self::MAX_TAGS_BYTES);
            $majorTags = $this->parseTags($contents, $major === self::NEXT_MAJOR);
            $matched = false;

            foreach ($majorTags as $tag) {
                if ($tag['version']['major'] !== $major) {
                    // Matching refs are prefix based. Ignore an unexpected
                    // neighbouring major rather than allowing it to affect a
                    // report (the trailing dot in tagsUrl normally prevents
                    // this response shape).
                    continue;
                }

                $matched = true;
                $tags[$tag['name']] = $tag;
            }

            if (! $matched && $major !== self::NEXT_MAJOR) {
                throw new RuntimeException(sprintf('The Laravel tags source for major %d contains no matching semantic-version tags.', $major));
            }
        }

        if ($tags === []) {
            throw new RuntimeException('The Laravel tags sources contain no valid semantic-version tags.');
        }

        /** @var list<array{name: string, version: array{major: int, minor: int, patch: int, pre: list<string>}}> $tags */
        return array_values($tags);
    }

    private function tagNameFromRef(string $ref): ?string
    {
        if (! str_starts_with($ref, 'refs/tags/')) {
            return null;
        }

        $name = substr($ref, strlen('refs/tags/'));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<array{name: string, version: array{major: int, minor: int, patch: int, pre: list<string>}}>  $tags
     * @return list<array<string, mixed>>
     */
    private function tagDrift(array $manifest, array $tags): array
    {
        $drift = [];
        $manifestMajors = [];

        foreach ($manifest as $major => $entry) {
            if (preg_match('/^\d+$/', (string) $major) !== 1 || ! is_array($entry)) {
                continue;
            }

            $tag = $entry['tag'] ?? null;
            $version = is_string($tag) ? self::parseVersion($tag) : null;

            if ($version === null) {
                continue;
            }

            $manifestMajor = (int) $major;
            $manifestMajors[] = $manifestMajor;
            $latest = null;

            foreach ($tags as $candidate) {
                if ($candidate['version']['major'] !== $manifestMajor) {
                    continue;
                }

                if ($latest === null || self::compareVersions($candidate['version'], $latest['version']) > 0) {
                    $latest = $candidate;
                }
            }

            if ($latest !== null && self::compareVersions($latest['version'], $version) > 0) {
                $drift[] = [
                    'type' => 'skeleton-tag',
                    'major' => $manifestMajor,
                    'snapshotTag' => $tag,
                    'latestTag' => $latest['name'],
                    'source' => self::tagsUrl($manifestMajor),
                    'snapshot' => 'resources/skeletons/MANIFEST.json',
                ];
            }
        }

        $highestManifestMajor = $manifestMajors === [] ? 0 : max($manifestMajors);
        $newMajorTags = [];

        foreach ($tags as $tag) {
            if ($tag['version']['major'] !== self::NEXT_MAJOR || $tag['version']['major'] <= $highestManifestMajor) {
                continue;
            }

            $major = $tag['version']['major'];

            if (! isset($newMajorTags[$major]) || self::compareVersions($tag['version'], $newMajorTags[$major]['version']) > 0) {
                $newMajorTags[$major] = $tag;
            }
        }

        foreach ($newMajorTags as $major => $tag) {
            $drift[] = [
                'type' => 'skeleton-tag-new-major',
                'major' => (int) $major,
                'latestTag' => $tag['name'],
                'source' => self::tagsUrl((int) $major),
                'snapshot' => 'resources/skeletons/MANIFEST.json',
            ];
        }

        return $drift;
    }

    private static function tagsUrl(int $major): string
    {
        return self::TAGS_URL.$major.'.';
    }

    /** @param array<string, mixed> $item */
    private static function sortPart(array $item, string $key): string
    {
        $value = $item[$key] ?? null;

        return match (true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            default => '',
        };
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     * @return list<string>
     */
    private function headingDifference(array $left, array $right): array
    {
        $difference = array_values(array_diff($left, $right));
        sort($difference, SORT_STRING);

        return $difference;
    }

    private function readFile(string $relative, int $maxBytes): string
    {
        SafePath::assertInsideRoot($this->root, $relative, 'repository source');
        $path = $this->root.'/'.$relative;

        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Repository source "%s" is missing.', $relative));
        }

        $size = filesize($path);

        if ($size === false || $size > $maxBytes) {
            throw new RuntimeException(sprintf('Repository source "%s" exceeds the %d-byte limit.', $relative, $maxBytes));
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            throw new RuntimeException(sprintf('Repository source "%s" is empty or unreadable.', $relative));
        }

        return $contents;
    }

    /**
     * @param  array<string, string>  $contents
     */
    private function writeSnapshots(array $contents): void
    {
        $directory = $this->root.'/resources/guides';
        SafePath::assertInsideRoot($this->root, 'resources/guides', 'snapshot directory');

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create snapshot directory "%s".', $directory));
        }

        foreach (self::GUIDE_SOURCES as $key => $source) {
            $path = $this->root.'/'.$source['snapshot'];

            if (is_link($path)) {
                throw new RuntimeException(sprintf('Refusing to overwrite symlink snapshot "%s".', $source['snapshot']));
            }

            $temporary = tempnam($directory, '.guide-drift-');

            if ($temporary === false) {
                throw new RuntimeException(sprintf('Could not create a temporary snapshot for "%s".', $source['snapshot']));
            }

            try {
                if (file_put_contents($temporary, $contents[$key], LOCK_EX) === false || ! rename($temporary, $path)) {
                    throw new RuntimeException(sprintf('Could not atomically write snapshot "%s".', $source['snapshot']));
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
    }

    /**
     * @return array{major: int, minor: int, patch: int, pre: list<string>}|null
     */
    private static function parseVersion(string $tag): ?array
    {
        if (preg_match('/^v?(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z.-]+))?(?:\+[0-9A-Za-z.-]+)?$/', trim($tag), $matches) !== 1) {
            return null;
        }

        $pre = isset($matches[4]) ? explode('.', $matches[4]) : [];

        return [
            'major' => (int) $matches[1],
            'minor' => (int) $matches[2],
            'patch' => (int) $matches[3],
            'pre' => $pre,
        ];
    }

    /**
     * @param  array{major: int, minor: int, patch: int, pre: list<string>}  $left
     * @param  array{major: int, minor: int, patch: int, pre: list<string>}  $right
     */
    private static function compareVersions(array $left, array $right): int
    {
        foreach (['major', 'minor', 'patch'] as $part) {
            if ($left[$part] !== $right[$part]) {
                return $left[$part] <=> $right[$part];
            }
        }

        if ($left['pre'] === [] && $right['pre'] !== []) {
            return 1;
        }

        if ($left['pre'] !== [] && $right['pre'] === []) {
            return -1;
        }

        $parts = max(count($left['pre']), count($right['pre']));

        for ($index = 0; $index < $parts; $index++) {
            $leftPart = $left['pre'][$index] ?? null;
            $rightPart = $right['pre'][$index] ?? null;

            if ($leftPart === $rightPart) {
                continue;
            }

            if ($leftPart === null || $rightPart === null) {
                return $leftPart === null ? -1 : 1;
            }

            if (is_numeric($leftPart) && is_numeric($rightPart)) {
                return ((int) $leftPart) <=> ((int) $rightPart);
            }

            if (is_numeric($leftPart)) {
                return -1;
            }

            if (is_numeric($rightPart)) {
                return 1;
            }

            return $leftPart <=> $rightPart;
        }

        return count($left['pre']) <=> count($right['pre']);
    }

    private static function displayHeading(string $token): string
    {
        [$level, $heading] = explode(':', $token, 2);

        return str_repeat('#', (int) $level).' '.$heading;
    }
}
