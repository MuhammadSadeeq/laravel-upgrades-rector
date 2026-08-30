<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use Closure;
use Composer\MetadataMinifier\MetadataMinifier;
use Composer\Semver\VersionParser;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\HttpSourceException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\HttpSourceFetcher;
use RuntimeException;
use Throwable;

/**
 * Reads Composer 2 (p2) package metadata from Packagist.
 *
 * Packagist has returned both a single p2 document and paginated documents
 * over time. This client accepts the documented pagination shapes while
 * keeping a strict page/byte budget so a maintenance job cannot consume an
 * unbounded response. A transport callback is available to make all tests
 * hermetic; it has the same shape as HttpSourceFetcher's callback.
 */
final class PackagistClient
{
    public const BASE_URL = 'https://repo.packagist.org/p2/';

    private const MAX_PAGE_BYTES = 8_388_608;

    private const MAX_TOTAL_BYTES = 32_000_000;

    private const MAX_PAGES = 100;

    /** @var list<string> */
    private const LARAVEL_REQUIREMENTS = [
        'illuminate/support',
        'illuminate/contracts',
        'illuminate/database',
        'laravel/framework',
    ];

    private const MAX_ATTEMPTS = 3;

    private const INITIAL_BACKOFF_MILLISECONDS = 100;

    private const MAX_BACKOFF_MILLISECONDS = 2_000;

    private const MAX_RETRY_AFTER_MILLISECONDS = 5_000;

    private readonly ?Closure $transport;

    /** @var Closure(int): void */
    private readonly Closure $sleeper;

    private readonly int $maxAttempts;

    /**
     * @param  Closure(string, int): array{status: int, body: string, headers?: list<string>}|null  $transport
     * @param  Closure(int): void|null  $sleeper  Receives a bounded delay in milliseconds.
     */
    public function __construct(?Closure $transport = null, ?Closure $sleeper = null, int $maxAttempts = self::MAX_ATTEMPTS)
    {
        if ($maxAttempts < 1 || $maxAttempts > self::MAX_ATTEMPTS) {
            throw new RuntimeException(sprintf('Packagist retry attempts must be between 1 and %d.', self::MAX_ATTEMPTS));
        }

        $this->transport = $transport;
        $this->sleeper = $sleeper ?? static function (int $milliseconds): void {
            if ($milliseconds > 0) {
                usleep($milliseconds * 1_000);
            }
        };
        $this->maxAttempts = $maxAttempts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function releases(string $package): array
    {
        if (preg_match('/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/i', $package) !== 1) {
            throw new RuntimeException(sprintf('Invalid Packagist package name "%s".', $package));
        }

        $url = self::BASE_URL.$package.'.json';
        $seenUrls = [];
        /** @var array<string, array<string, mixed>> $releasesByIdentity */
        $releasesByIdentity = [];
        $totalBytes = 0;

        for ($page = 0; $url !== null; $page++) {
            if ($page >= self::MAX_PAGES) {
                throw new RuntimeException(sprintf('Packagist response for "%s" exceeded the %d-page limit.', $package, self::MAX_PAGES));
            }

            if (isset($seenUrls[$url])) {
                throw new RuntimeException(sprintf('Packagist response for "%s" contains a pagination loop.', $package));
            }

            $seenUrls[$url] = true;
            $body = $this->fetch($url);
            $totalBytes += strlen($body);

            if ($totalBytes > self::MAX_TOTAL_BYTES) {
                throw new RuntimeException(sprintf('Packagist response for "%s" exceeds the %d-byte total limit.', $package, self::MAX_TOTAL_BYTES));
            }

            try {
                /** @var mixed $decoded */
                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                throw new RuntimeException(sprintf('Packagist response for "%s" is invalid JSON: %s', $package, $exception->getMessage()), 0, $exception);
            }

            if (! is_array($decoded)) {
                throw new RuntimeException(sprintf('Packagist response for "%s" must be a JSON object.', $package));
            }

            $minified = $this->minifiedPage($decoded, $package);
            $pageReleases = $this->pageReleases($this->object($decoded, $package), $package, $minified);

            foreach ($pageReleases as $release) {
                $identity = $this->releaseIdentity($release);

                if (isset($releasesByIdentity[$identity])) {
                    if ($this->relevantRequirements($release) !== $this->relevantRequirements($releasesByIdentity[$identity])) {
                        throw new RuntimeException(sprintf('Packagist response for "%s" contains conflicting duplicate metadata for normalized release "%s".', $package, $identity));
                    }

                    if ($this->canonical($release) < $this->canonical($releasesByIdentity[$identity])) {
                        $releasesByIdentity[$identity] = $release;
                    }
                } else {
                    $releasesByIdentity[$identity] = $release;
                }
            }

            $url = $this->nextUrl($this->object($decoded, $package), $url, $package);
        }

        if ($releasesByIdentity === []) {
            throw new RuntimeException(sprintf('Packagist response for "%s" contained no releases.', $package));
        }

        ksort($releasesByIdentity, SORT_STRING);

        return array_values($releasesByIdentity);
    }

    private function fetch(string $url): string
    {
        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                return $this->fetchOnce($url);
            } catch (Throwable $exception) {
                $failure = $exception instanceof RuntimeException
                    ? $exception
                    : new RuntimeException(sprintf('Could not fetch Packagist metadata from "%s": %s', $url, $exception->getMessage()), 0, $exception);

                if (! $failure instanceof HttpSourceException || $attempt >= $this->maxAttempts || ! $failure->transient) {
                    throw $failure;
                }

                ($this->sleeper)($this->retryDelay($failure, $attempt));
            }
        }

        throw new RuntimeException(sprintf('Could not fetch Packagist metadata from "%s".', $url));
    }

    private function fetchOnce(string $url): string
    {
        try {
            if ($this->transport !== null) {
                return (new HttpSourceFetcher($this->transport))->fetch($url, self::MAX_PAGE_BYTES);
            }

            return (new HttpSourceFetcher)->fetch($url, self::MAX_PAGE_BYTES);
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException(sprintf('Could not fetch Packagist metadata from "%s": %s', $url, $exception->getMessage()), 0, $exception);
        }
    }

    private function retryDelay(HttpSourceException $exception, int $attempt): int
    {
        $retryAfterHeader = $exception->retryAfter;

        if ($retryAfterHeader !== null && preg_match('/^[0-9]+(?:\.[0-9]+)?$/', trim($retryAfterHeader)) === 1) {
            $retryAfter = (int) ceil((float) trim($retryAfterHeader) * 1_000);

            return min(self::MAX_RETRY_AFTER_MILLISECONDS, max(0, $retryAfter));
        }

        if ($retryAfterHeader !== null && trim($retryAfterHeader) !== '') {
            $retryAt = strtotime(trim($retryAfterHeader));

            if ($retryAt !== false) {
                return min(self::MAX_RETRY_AFTER_MILLISECONDS, max(0, ($retryAt - time()) * 1_000));
            }
        }

        $exponent = min(10, max(0, $attempt - 1));
        $delay = self::INITIAL_BACKOFF_MILLISECONDS * (2 ** $exponent);

        return min(self::MAX_BACKOFF_MILLISECONDS, $delay);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    private function pageReleases(array $decoded, string $package, bool $minified): array
    {
        $packages = $decoded['packages'] ?? null;

        if (! is_array($packages)) {
            // A few mirrors expose the old Composer repository shape as
            // {"package": [{...}]}; accepting it costs no ambiguity.
            $packages = $decoded['package'] ?? null;

            if (is_array($packages)) {
                return $this->validateReleases($this->releaseList($packages, $package, $minified), $package);
            }

            throw new RuntimeException(sprintf('Packagist response for "%s" is missing a packages list.', $package));
        }

        if (array_is_list($packages)) {
            return $this->validateReleases($this->releaseList($packages, $package, $minified), $package);
        }

        if (array_key_exists('name', $packages) && (array_key_exists('version', $packages) || array_key_exists('version_normalized', $packages))) {
            return $this->validateReleases($this->releaseList($packages, $package, $minified), $package);
        }

        $entries = $packages[$package] ?? null;

        if ($entries === null) {
            // Be tolerant of a server changing only the case of a package
            // key, but do not silently accept metadata for another package.
            foreach ($packages as $name => $candidate) {
                if (is_string($name) && strcasecmp($name, $package) === 0) {
                    $entries = $candidate;

                    break;
                }
            }
        }

        if (! is_array($entries)) {
            throw new RuntimeException(sprintf('Packagist response for "%s" is missing its release list.', $package));
        }

        return $this->validateReleases($this->releaseList($entries, $package, $minified), $package);
    }

    /**
     * Convert both the current p2 list shape and old version-keyed mirrors to
     * a list. The latter is useful for fixtures and costs no ambiguity because
     * every value must still be a release object.
     *
     * @param  array<mixed>  $entries
     * @return list<mixed>
     */
    private function releaseList(array $entries, string $package, bool $minified = false): array
    {
        if (array_key_exists('name', $entries) && (array_key_exists('version', $entries) || array_key_exists('version_normalized', $entries))) {
            $releaseList = [$entries];

            return $minified ? $this->expandMinified($releaseList, $package) : $releaseList;
        }

        if (array_is_list($entries)) {
            return $minified ? $this->expandMinified($entries, $package) : $entries;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException(sprintf('Packagist response for "%s" has a malformed version-keyed release list.', $package));
            }
        }

        $releaseList = array_values($entries);

        return $minified ? $this->expandMinified($releaseList, $package) : $releaseList;
    }

    /** @param array<mixed, mixed> $decoded */
    private function minifiedPage(array $decoded, string $package): bool
    {
        if (! array_key_exists('minified', $decoded)) {
            return false;
        }

        if (! is_string($decoded['minified']) || $decoded['minified'] !== 'composer/2.0') {
            throw new RuntimeException(sprintf('Packagist response for "%s" has an unsupported minified marker.', $package));
        }

        return true;
    }

    /**
     * @param  list<mixed>  $entries
     * @return list<mixed>
     */
    private function expandMinified(array $entries, string $package): array
    {
        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException(sprintf('Packagist response for "%s" has a malformed minified release at index %s.', $package, (string) $index));
            }
        }

        try {
            /** @var list<mixed> $expanded */
            $expanded = MetadataMinifier::expand($entries);

            return $expanded;
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('Packagist response for "%s" could not expand composer/2.0 metadata: %s', $package, $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * @param  list<mixed>  $entries
     * @return list<array<string, mixed>>
     */
    private function validateReleases(array $entries, string $package): array
    {
        /** @var list<array<string, mixed>> $releases */
        $releases = [];

        foreach ($entries as $index => $rawEntry) {
            if (! is_array($rawEntry)) {
                throw new RuntimeException(sprintf('Packagist response for "%s" has a malformed release at index %s.', $package, (string) $index));
            }

            $entry = $this->object($rawEntry, $package);

            $releaseName = $entry['name'] ?? null;

            if (! is_string($releaseName)) {
                throw new RuntimeException(sprintf('Packagist response for "%s" has a release without a name.', $package));
            }

            if ($releaseName !== $package && strcasecmp($releaseName, $package) !== 0) {
                throw new RuntimeException(sprintf('Packagist response for "%s" contains metadata for "%s".', $package, $releaseName));
            }

            // Every release must identify a non-empty, Composer-parseable
            // version. When both aliases are supplied, they must describe the
            // same normalized Composer version rather than silently selecting
            // one of two conflicting identities.
            $this->validateVersionFields($entry, $package, $index);

            if (! array_key_exists('version', $entry) && ! array_key_exists('version_normalized', $entry)) {
                throw new RuntimeException(sprintf('Packagist response for "%s" has a release without a version at index %s.', $package, $index));
            }

            $releases[] = $entry;
        }

        return $releases;
    }

    /** @param array<string, mixed> $entry */
    private function validateVersionFields(array $entry, string $package, int|string $index): void
    {
        $normalized = [];

        foreach (['version', 'version_normalized'] as $field) {
            if (! array_key_exists($field, $entry)) {
                continue;
            }

            $value = $entry[$field];

            if (! is_string($value) || trim($value) === '') {
                throw new RuntimeException(sprintf('Packagist response for "%s" has an invalid %s at index %s.', $package, $field, (string) $index));
            }

            try {
                $normalized[$field] = (new VersionParser)->normalize(trim($value));
            } catch (Throwable $exception) {
                throw new RuntimeException(sprintf('Packagist response for "%s" has an invalid %s at index %s: %s', $package, $field, (string) $index, $exception->getMessage()), 0, $exception);
            }
        }

        if ($normalized === []) {
            throw new RuntimeException(sprintf('Packagist response for "%s" has a release without a version at index %s.', $package, (string) $index));
        }

        if (isset($normalized['version'], $normalized['version_normalized']) && $normalized['version'] !== $normalized['version_normalized']) {
            throw new RuntimeException(sprintf('Packagist response for "%s" has conflicting version fields at index %s.', $package, (string) $index));
        }
    }

    /**
     * @param  array<mixed, mixed>  $value
     * @return array<string, mixed>
     */
    private function object(array $value, string $package): array
    {
        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new RuntimeException(sprintf('Packagist response for "%s" contains a JSON array where an object was expected.', $package));
            }

            $object[$key] = $item;
        }

        return $object;
    }

    /** @param array<string, mixed> $release */
    private function releaseIdentity(array $release): string
    {
        $version = $release['version_normalized'] ?? $release['version'] ?? '';

        if (! is_string($version)) {
            return '';
        }

        try {
            // Composer considers aliases such as v1.0.0, 1.0 and 1.0.0.0
            // the same release identity after normalization.
            return (new VersionParser)->normalize(trim($version));
        } catch (Throwable) {
            return strtolower(trim($version));
        }
    }

    /** @param array<string, mixed> $release */
    private function relevantRequirements(array $release): string
    {
        $require = array_key_exists('require', $release) ? $release['require'] : [];

        if (! is_array($require)) {
            return 'invalid:'.$this->canonical($require);
        }

        $relevant = [];

        foreach (self::LARAVEL_REQUIREMENTS as $name) {
            if (array_key_exists($name, $require)) {
                $relevant[$name] = $require[$name];
            }
        }

        return $this->canonical($relevant);
    }

    /** Return a stable representation for duplicate release selection. */
    private function canonical(mixed $value): string
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }

            $parts = [];

            foreach ($value as $key => $item) {
                $parts[] = is_int($key) ? $this->canonical($item) : $this->canonical((string) $key).':'.$this->canonical($item);
            }

            return '['.implode(',', $parts).']';
        }

        try {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return get_debug_type($value);
        }
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function nextUrl(array $decoded, string $currentUrl, string $package): ?string
    {
        $next = $decoded['next'] ?? null;

        if ($next === null && is_array($decoded['pagination'] ?? null)) {
            $next = $decoded['pagination']['next'] ?? null;
        }

        if ($next === null && is_array($decoded['links'] ?? null)) {
            $next = $decoded['links']['next'] ?? null;
        }

        if ($next === null || $next === '') {
            return null;
        }

        if (is_array($next)) {
            $next = $next['url'] ?? null;
        }

        if (! is_string($next)) {
            throw new RuntimeException(sprintf('Packagist response for "%s" has a malformed pagination URL.', $package));
        }

        $next = trim($next);
        $current = parse_url($currentUrl);

        if ($next === '' || ! is_array($current) || ! is_string($current['path'] ?? null)) {
            throw new RuntimeException(sprintf('Packagist response for "%s" has an unsafe pagination URL.', $package));
        }

        if (str_starts_with($next, '//')) {
            throw new RuntimeException(sprintf('Packagist response for "%s" has an unsafe pagination URL.', $package));
        }

        $nextParts = parse_url($next);

        if ($nextParts === false) {
            throw new RuntimeException(sprintf('Packagist response for "%s" has an unsafe pagination URL.', $package));
        }

        if (isset($nextParts['scheme']) || isset($nextParts['host'])) {
            $scheme = strtolower((string) ($nextParts['scheme'] ?? ''));
            $host = strtolower((string) ($nextParts['host'] ?? ''));
            $path = $nextParts['path'] ?? null;

            if ($scheme !== 'https' || $host !== 'repo.packagist.org'
                || array_key_exists('user', $nextParts)
                || array_key_exists('pass', $nextParts)
                || array_key_exists('port', $nextParts)
                || ! is_string($path)) {
                throw new RuntimeException(sprintf('Packagist response for "%s" points pagination at an unexpected host.', $package));
            }
        } else {
            $path = $nextParts['path'] ?? '';

            if (! is_string($path)) {
                throw new RuntimeException(sprintf('Packagist response for "%s" has an unsafe pagination URL.', $package));
            }

            if (str_starts_with($next, '?')) {
                $path = $current['path'];
            } elseif (! str_starts_with($path, '/')) {
                $baseDirectory = substr($current['path'], 0, (int) strrpos($current['path'], '/') + 1);
                $path = $baseDirectory.$path;
            }
        }

        if (isset($nextParts['fragment'])) {
            throw new RuntimeException(sprintf('Packagist response for "%s" has an unsafe pagination URL.', $package));
        }

        $path = rawurldecode($path);
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new RuntimeException(sprintf('Packagist response for "%s" has an unsafe pagination URL.', $package));
            }

            $segments[] = $segment;
        }

        $normalizedPath = '/'.implode('/', $segments);

        if (! str_starts_with($normalizedPath, '/p2/')) {
            throw new RuntimeException(sprintf('Packagist response for "%s" has an unsafe pagination path.', $package));
        }

        $query = isset($nextParts['query']) ? '?'.$nextParts['query'] : '';
        $resolved = 'https://repo.packagist.org'.$normalizedPath.$query;

        if (filter_var($resolved, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException(sprintf('Packagist response for "%s" has an unsafe pagination URL.', $package));
        }

        return $resolved;
    }
}
