<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Support\Compat\CompatFileLoader;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use RuntimeException;

/**
 * Strict, fail-closed loader for package-major guidance.
 *
 * Invalid shipped data is a package defect, not a reason to silently omit
 * upgrade advice. Callers should surface the exception as a failed preflight
 * or dependency step before changing the project.
 */
final class PackageGuideCatalog
{
    /** @var array<string, PackageGuide>|null */
    private ?array $guides = null;

    public function __construct(private readonly string $jsonPath) {}

    /** @return array<string, PackageGuide> */
    public function all(): array
    {
        if ($this->guides === null) {
            $this->guides = $this->load();
        }

        return $this->guides;
    }

    public function forPackage(string $package): ?PackageGuide
    {
        return $this->all()[$package] ?? null;
    }

    /** @return array<string, PackageGuide> */
    private function load(): array
    {
        $schemaPath = dirname($this->jsonPath).'/package-guides.schema.json';

        // The data file is only trusted when its sibling schema is present
        // and validates it. A missing schema must fail closed rather than
        // silently downgrading runtime enforcement to handwritten checks.
        (new PackageGuideSchemaValidator)->validate($this->jsonPath, $schemaPath);

        $document = CompatFileLoader::load($this->jsonPath, '');
        $this->assertKeys($document, ['$schema', 'schemaVersion', 'packages'], 'document');

        if (! is_string($document['$schema'] ?? null)
            || preg_match('/^(?:https?:\/\/[^\s]+|\.\/[^\s]+)$/', trim($document['$schema'])) !== 1) {
            throw $this->invalid('$schema must be a non-empty string');
        }

        if (($document['schemaVersion'] ?? null) !== 1) {
            throw $this->invalid('schemaVersion must be 1');
        }

        $packages = $document['packages'] ?? null;

        if (! is_array($packages) || array_is_list($packages) || count($packages) === 0) {
            throw $this->invalid('packages must be a non-empty object');
        }

        /** @var array<string, PackageGuide> $guides */
        $guides = [];

        foreach ($packages as $package => $entry) {
            if (! is_string($package) || preg_match('/^[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?\/[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?$/', $package) !== 1) {
                throw $this->invalid('package names must use Composer vendor/name syntax');
            }

            if (! is_array($entry) || array_is_list($entry)) {
                throw $this->invalid(sprintf('package "%s" must be an object', $package));
            }

            $this->assertKeys($entry, ['guideUrl', 'majors'], $package);
            $guideUrl = $this->url($entry['guideUrl'] ?? null, sprintf('%s guideUrl', $package));
            $majors = $entry['majors'] ?? null;

            if (! is_array($majors) || $majors === []) {
                throw $this->invalid(sprintf('%s majors must be a non-empty object', $package));
            }

            /** @var array<int, PackageGuideMajor> $typedMajors */
            $typedMajors = [];

            foreach ($majors as $majorKey => $majorEntry) {
                if (preg_match('/^[1-9][0-9]*$/', (string) $majorKey) !== 1) {
                    throw $this->invalid(sprintf('%s major keys must be positive integers', $package));
                }

                $majorKey = (string) $majorKey;

                if (! is_array($majorEntry) || array_is_list($majorEntry)) {
                    throw $this->invalid(sprintf('%s major %s must be an object', $package, $majorKey));
                }

                $this->assertKeys($majorEntry, ['guideUrl', 'items', 'counter', 'status', 'notes'], sprintf('%s major %s', $package, $majorKey));
                $major = (int) $majorKey;
                $majorUrl = array_key_exists('guideUrl', $majorEntry)
                    ? $this->url($majorEntry['guideUrl'], sprintf('%s major %s guideUrl', $package, $majorKey))
                    : $guideUrl;
                $items = $majorEntry['items'] ?? null;

                if (! is_array($items) || ! array_is_list($items) || $items === []) {
                    throw $this->invalid(sprintf('%s major %s items must be a non-empty list', $package, $majorKey));
                }

                /** @var list<PackageGuideItem> $typedItems */
                $typedItems = [];

                foreach ($items as $index => $item) {
                    if (! is_array($item) || array_is_list($item)) {
                        throw $this->invalid(sprintf('%s major %s item %s must be an object', $package, $majorKey, $index));
                    }

                    $this->assertKeys($item, ['id', 'severity', 'message', 'action', 'guideUrl'], sprintf('%s major %s item %s', $package, $majorKey, $index));
                    $id = $item['id'] ?? null;
                    $severity = $item['severity'] ?? null;
                    $message = $item['message'] ?? null;
                    $action = $item['action'] ?? null;

                    if (! is_string($id) || preg_match('/^[A-Za-z0-9_.-]+$/', $id) !== 1
                        || ! is_string($severity) || ! in_array($severity, [
                            Finding::SEVERITY_HIGH,
                            Finding::SEVERITY_MEDIUM,
                            Finding::SEVERITY_LOW,
                            Finding::SEVERITY_INFO,
                        ], true)
                        || ! is_string($message) || trim($message) === ''
                        || ! is_string($action) || trim($action) === '') {
                        throw $this->invalid(sprintf('%s major %s item %s has invalid fields', $package, $majorKey, $index));
                    }

                    $itemUrl = array_key_exists('guideUrl', $item)
                        ? $this->url($item['guideUrl'], sprintf('%s major %s item %s guideUrl', $package, $majorKey, $index))
                        : null;
                    $typedItems[] = new PackageGuideItem($id, $severity, $message, $action, $itemUrl);
                }

                $counter = null;

                if (array_key_exists('counter', $majorEntry)) {
                    $counter = $this->counter($majorEntry['counter'], sprintf('%s major %s counter', $package, $majorKey));
                }

                $status = $majorEntry['status'] ?? 'supported';
                $notes = $majorEntry['notes'] ?? null;

                if (! is_string($status) || ! in_array($status, ['supported', 'future'], true)
                    || ($notes !== null && (! is_string($notes) || trim($notes) === ''))
                    || ($status === 'future' && ! is_string($notes))) {
                    throw $this->invalid(sprintf('%s major %s status/notes are invalid', $package, $majorKey));
                }

                $typedMajors[$major] = new PackageGuideMajor($major, $majorUrl, $typedItems, $counter, $status, $notes);
            }

            ksort($typedMajors);
            $guides[$package] = new PackageGuide($package, $guideUrl, $typedMajors);
        }

        ksort($guides);

        return $guides;
    }

    private function url(mixed $value, string $field): string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false
            || ! in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw $this->invalid($field.' must be an absolute HTTP(S) URL');
        }

        return $value;
    }

    private function counter(mixed $value, string $field): PackageGuideCounter
    {
        if (! is_array($value) || array_is_list($value)
            || ! is_string($value['label'] ?? null) || trim($value['label']) === ''
            || ! is_array($value['paths'] ?? null) || ! array_is_list($value['paths']) || $value['paths'] === []
            || ! is_array($value['extensions'] ?? null) || ! array_is_list($value['extensions']) || $value['extensions'] === []) {
            throw $this->invalid($field.' must contain label, paths, and extensions lists');
        }

        $this->assertKeys($value, ['label', 'paths', 'extensions'], $field);

        $paths = [];

        foreach ($value['paths'] as $path) {
            $path = is_string($path) ? trim($path) : '';

            if ($path === '' || $path === '.' || str_starts_with($path, '/')
                || str_contains($path, '\\') || preg_match('/^[a-z]:/i', $path) === 1
                || str_contains($path, '..')) {
                throw $this->invalid($field.' paths must be POSIX-relative to the project and cannot contain .., backslashes, or drive prefixes');
            }

            $paths[] = trim($path, '/');
        }

        $extensions = [];

        foreach ($value['extensions'] as $extension) {
            $extension = is_string($extension) ? trim($extension) : '';

            $normalizedExtension = ltrim(strtolower($extension), '.');

            if ($normalizedExtension === '' || str_contains($normalizedExtension, '/')
                || preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/i', $normalizedExtension) !== 1) {
                throw $this->invalid($field.' extensions must be non-empty suffixes');
            }

            $extensions[] = $normalizedExtension;
        }

        return new PackageGuideCounter($value['label'], array_values(array_unique($paths)), array_values(array_unique($extensions)));
    }

    private function invalid(string $message): RuntimeException
    {
        return new RuntimeException(sprintf('Invalid package guide data in "%s": %s.', $this->jsonPath, $message));
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  list<string>  $allowed
     */
    private function assertKeys(array $value, array $allowed, string $field): void
    {
        foreach (array_keys($value) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw $this->invalid(sprintf('%s contains unsupported field "%s"', $field, (string) $key));
            }
        }
    }
}
