<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use DateTimeImmutable;
use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\SafePath;
use RuntimeException;

/**
 * Validates a generated matrix against the reviewed checked-in baseline.
 * This class is read-only and intentionally uses only PHP's JSON/date
 * facilities so it can run in a write-capable CI job before dependencies are
 * installed.
 */
final class CompatibilityMatrixArtifactValidator
{
    private const MAX_JSON_BYTES = 4_194_304;

    /**
     * @throws RuntimeException when the candidate is malformed or changes the
     *                          reviewed matrix structure/metadata.
     */
    public function validate(string $baselinePath, string $candidatePath): void
    {
        $baseline = $this->readJson($baselinePath, 'compatibility matrix baseline');
        $candidate = $this->readJson($candidatePath, 'compatibility matrix artifact');

        $this->validateDocument($baseline, 'compatibility matrix baseline');
        $this->validateDocument($candidate, 'compatibility matrix artifact');

        if (array_keys($baseline) !== array_keys($candidate)) {
            throw new RuntimeException('Compatibility matrix artifact changed its top-level key structure.');
        }

        foreach ($baseline as $key => $value) {
            if ($key !== 'generatedAt' && $key !== 'packages' && $candidate[$key] !== $value) {
                throw new RuntimeException(sprintf('Compatibility matrix artifact changed top-level metadata "%s".', (string) $key));
            }
        }

        /** @var array<string, array<string, mixed>> $baselinePackages */
        $baselinePackages = $baseline['packages'];
        /** @var array<string, array<string, mixed>> $candidatePackages */
        $candidatePackages = $candidate['packages'];

        if (array_keys($baselinePackages) !== array_keys($candidatePackages)) {
            throw new RuntimeException('Compatibility matrix artifact changed the package key set or order.');
        }

        foreach ($baselinePackages as $package => $baselineEntry) {
            $candidateEntry = $candidatePackages[$package];

            if (array_keys($baselineEntry) !== array_keys($candidateEntry)) {
                throw new RuntimeException(sprintf('Compatibility matrix artifact changed the key structure for "%s".', $package));
            }

            foreach ($baselineEntry as $key => $value) {
                if (! $this->isMajorKey($key) && $candidateEntry[$key] !== $value) {
                    throw new RuntimeException(sprintf('Compatibility matrix artifact changed metadata for "%s".', $package));
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function readJson(string $path, string $description): array
    {
        $absolute = $this->absolutePath($path, $description);
        SafePath::assertNoSymlinkComponents($absolute, $description);

        if (! is_file($absolute)) {
            throw new RuntimeException(sprintf('%s "%s" was not found.', $description, $path));
        }

        SafePath::assertNoSymlinkComponents($absolute, $description);
        $size = filesize($absolute);

        if ($size === false || $size > self::MAX_JSON_BYTES) {
            throw new RuntimeException(sprintf('%s "%s" exceeds the %d-byte limit.', $description, $path, self::MAX_JSON_BYTES));
        }

        SafePath::assertNoSymlinkComponents($absolute, $description);
        $contents = file_get_contents($absolute);

        if ($contents === false || $contents === '') {
            throw new RuntimeException(sprintf('%s "%s" is empty or unreadable.', $description, $path));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('%s "%s" contains invalid JSON: %s', $description, $path, $exception->getMessage()), 0, $exception);
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException(sprintf('%s "%s" must contain a JSON object.', $description, $path));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** @param array<string, mixed> $document */
    private function validateDocument(array $document, string $description): void
    {
        if (! array_key_exists('generatedAt', $document) || ! is_string($document['generatedAt']) || ! $this->isCalendarDate($document['generatedAt'])) {
            throw new RuntimeException(sprintf('%s must contain a valid generatedAt YYYY-MM-DD date.', $description));
        }

        $packages = $document['packages'] ?? null;

        if (! is_array($packages) || $packages === [] || array_is_list($packages)) {
            throw new RuntimeException(sprintf('%s must contain a non-empty associative packages object.', $description));
        }

        foreach ($packages as $package => $entry) {
            if (! is_string($package) || ! is_array($entry) || $entry === [] || array_is_list($entry)) {
                throw new RuntimeException(sprintf('%s contains a malformed package entry.', $description));
            }

            foreach ($entry as $key => $value) {
                if (! $this->isMajorKey($key)) {
                    continue;
                }

                if (! is_string($value) || preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $value) !== 1) {
                    throw new RuntimeException(sprintf('%s contains an invalid stable version for "%s" Laravel major "%s".', $description, $package, (string) $key));
                }
            }
        }
    }

    private function isMajorKey(string|int $key): bool
    {
        return preg_match('/^[1-9]\d*$/', (string) $key) === 1;
    }

    private function isCalendarDate(string $date): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        return $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsed->format('Y-m-d') === $date;
    }

    private function absolutePath(string $path, string $description): string
    {
        if ($path === '' || str_contains($path, "\0") || preg_match('~(^|/)\.\.(/|$)~', $path) === 1) {
            throw new RuntimeException(sprintf('Unsafe %s path "%s".', $description, $path));
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        $workingDirectory = getcwd();

        if ($workingDirectory === false) {
            throw new RuntimeException(sprintf('Could not resolve the working directory for %s.', $description));
        }

        return $workingDirectory.'/'.$path;
    }
}
