<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * The package's single source of truth for implemented Laravel upgrade paths.
 *
 * Paths are ordered from oldest to newest. The value object intentionally
 * keeps the policy small and immutable so command validation, planning, and
 * advisory configuration cannot drift apart. Retirement is exposed as a
 * maintainer review predicate: this document cannot infer whether a
 * replacement was implemented from Git history or another external state.
 */
final class SupportPolicy
{
    public const SCHEMA = 'https://json-schema.org/draft/2020-12/schema';

    public const VERSION = 1;

    public const MAX_PATH_COUNT = 3;

    public const DEFAULT_PATH = __DIR__.'/../../resources/compat/support.json';

    /** @var list<array{source: int, target: int}> */
    private readonly array $paths;

    /** @var array<int, array{phpMinimum: string, securityFixUntil: string}> */
    private readonly array $sources;

    /**
     * @param  list<array{source: int, target: int}>  $paths
     * @param  array<int, array{phpMinimum: string, securityFixUntil: string}>  $sources
     */
    private function __construct(
        private readonly string $schema,
        private readonly int $version,
        private readonly int $maxPathCount,
        array $paths,
        array $sources,
    ) {
        $this->paths = array_values($paths);
        $this->sources = $sources;
    }

    public static function default(): self
    {
        return self::fromFile(self::DEFAULT_PATH);
    }

    public static function fromFile(string $path): self
    {
        return SupportPolicyLoader::load($path);
    }

    /**
     * Build and strictly validate a policy document decoded from JSON.
     *
     * @param  array<mixed, mixed>  $document
     */
    public static function fromArray(array $document): self
    {
        self::assertObject($document, 'top-level document');
        self::assertKeys($document, ['$schema', 'schemaVersion', 'maxPathCount', 'paths', 'sources'], 'top-level document');

        $schema = $document['$schema'] ?? null;

        if ($schema !== self::SCHEMA) {
            throw new InvalidArgumentException('Support policy has an unsupported schema.');
        }

        $version = $document['schemaVersion'] ?? null;

        if ($version !== self::VERSION) {
            throw new InvalidArgumentException('Support policy has an unsupported version.');
        }

        $maxPathCount = $document['maxPathCount'] ?? null;

        if (! is_int($maxPathCount) || $maxPathCount !== self::MAX_PATH_COUNT) {
            throw new InvalidArgumentException(sprintf('Support policy maxPathCount must be %d.', self::MAX_PATH_COUNT));
        }

        $rawPaths = $document['paths'] ?? null;

        if (! is_array($rawPaths) || ! array_is_list($rawPaths) || $rawPaths === []) {
            throw new InvalidArgumentException('Support policy paths must be a non-empty ordered list.');
        }

        if (count($rawPaths) > $maxPathCount) {
            throw new InvalidArgumentException(sprintf(
                'Support policy contains %d paths; the maximum is %d.',
                count($rawPaths),
                $maxPathCount,
            ));
        }

        $paths = [];

        foreach ($rawPaths as $index => $rawPath) {
            if (! is_array($rawPath) || ! self::isObject($rawPath)) {
                throw new InvalidArgumentException(sprintf('Support policy path %d must be an object.', $index));
            }

            self::assertKeys($rawPath, ['source', 'target'], sprintf('support policy path %d', $index));
            $source = $rawPath['source'] ?? null;
            $target = $rawPath['target'] ?? null;

            if (! is_int($source) || ! is_int($target)) {
                throw new InvalidArgumentException(sprintf('Support policy path %d source and target must be integers.', $index));
            }

            if ($target !== $source + 1) {
                throw new InvalidArgumentException(sprintf(
                    'Support policy path %d must be an adjacent Laravel major transition (%d -> %d).',
                    $index,
                    $source,
                    $target,
                ));
            }

            if ($source < 1 || $target < 2) {
                throw new InvalidArgumentException(sprintf('Support policy path %d contains invalid Laravel majors.', $index));
            }

            if (isset($paths[$index - 1]) && $paths[$index - 1]['target'] !== $source) {
                throw new InvalidArgumentException('Support policy paths must be ordered and contiguous.');
            }

            foreach ($paths as $existing) {
                if ($existing['source'] === $source || $existing['target'] === $target) {
                    throw new InvalidArgumentException('Support policy paths must have unique source and target majors.');
                }
            }

            $paths[] = ['source' => $source, 'target' => $target];
        }

        $rawSources = $document['sources'] ?? null;

        if (! is_array($rawSources) || ! self::isObject($rawSources) || $rawSources === []) {
            throw new InvalidArgumentException('Support policy sources must be a non-empty object.');
        }

        $sources = [];

        foreach ($rawSources as $majorKey => $rawSource) {
            if (preg_match('/^(?:0|[1-9]\d*)$/', (string) $majorKey) !== 1) {
                throw new InvalidArgumentException('Support policy source majors must be non-negative integer keys.');
            }

            $major = (int) $majorKey;

            if (! is_array($rawSource) || ! self::isObject($rawSource)) {
                throw new InvalidArgumentException(sprintf('Support policy source %d must be an object.', $major));
            }

            self::assertKeys($rawSource, ['phpMinimum', 'securityFixUntil'], sprintf('support policy source %d', $major));
            $phpMinimum = $rawSource['phpMinimum'] ?? null;
            $securityFixUntil = $rawSource['securityFixUntil'] ?? null;

            $phpMinimum = self::assertStablePhpVersion($phpMinimum, $major);
            $securityFixUntil = self::assertDate($securityFixUntil, $major);

            $sources[$major] = [
                'phpMinimum' => $phpMinimum,
                'securityFixUntil' => $securityFixUntil,
            ];
        }

        ksort($sources);
        $pathSources = array_map(static fn (array $path): int => $path['source'], $paths);
        $sourceMajors = array_keys($sources);

        if ($sourceMajors !== $pathSources) {
            throw new InvalidArgumentException('Support policy sources must exactly match path source majors in order.');
        }

        return new self($schema, $version, $maxPathCount, $paths, $sources);
    }

    public function schema(): string
    {
        return $this->schema;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function maxPathCount(): int
    {
        return $this->maxPathCount;
    }

    /** @return list<array{source: int, target: int}> */
    public function paths(): array
    {
        return $this->paths;
    }

    /** @return array<int, array{phpMinimum: string, securityFixUntil: string}> */
    public function sources(): array
    {
        return $this->sources;
    }

    /** @return list<int> */
    public function sourceMajors(): array
    {
        return array_map(static fn (array $path): int => $path['source'], $this->paths);
    }

    /** @return list<int> */
    public function targetMajors(): array
    {
        return array_map(static fn (array $path): int => $path['target'], $this->paths);
    }

    /** @return list<int> */
    public function supportedMajors(): array
    {
        return array_values(array_unique(array_merge($this->sourceMajors(), $this->targetMajors())));
    }

    public function oldestSourceMajor(): int
    {
        return $this->paths[0]['source'];
    }

    public function minTargetMajor(): int
    {
        return $this->targetMajors()[0];
    }

    public function newestSourceMajor(): int
    {
        return $this->paths[count($this->paths) - 1]['source'];
    }

    public function maxTargetMajor(): int
    {
        $last = $this->paths[count($this->paths) - 1];

        return $last['target'];
    }

    public function isSupportedSource(int $major): bool
    {
        return in_array($major, $this->sourceMajors(), true);
    }

    public function isSupportedTarget(int $major): bool
    {
        return in_array($major, $this->targetMajors(), true);
    }

    public function supportsMajor(int $major): bool
    {
        return in_array($major, $this->supportedMajors(), true);
    }

    public function sourcePhpMinimum(int $major): string
    {
        return $this->sourceEntry($major)['phpMinimum'];
    }

    public function oldestSourcePhpMinimum(): string
    {
        return $this->sourcePhpMinimum($this->oldestSourceMajor());
    }

    public function minimumPhpVersion(): string
    {
        return $this->oldestSourcePhpMinimum();
    }

    public function packagePhpConstraint(): string
    {
        // Keep the patch component so a future source floor such as 8.1.4
        // cannot be weakened accidentally to ^8.1 (which admits 8.1.0).
        return '^'.$this->minimumPhpVersion();
    }

    public function sourceSecurityFixUntil(int $major): string
    {
        return $this->sourceEntry($major)['securityFixUntil'];
    }

    public function oldestSourceSecurityFixUntil(): string
    {
        return $this->sourceSecurityFixUntil($this->oldestSourceMajor());
    }

    public function securityFixUntilDate(int $major): DateTimeImmutable
    {
        return new DateTimeImmutable($this->sourceSecurityFixUntil($major), new DateTimeZone('UTC'));
    }

    /**
     * A replacement path creates window pressure only when the current
     * maximum-sized window is full. The oldest path can then be retired only
     * strictly after its source major's security-fix date.
     */
    public function canRetireOldest(
        int $replacementSourceMajor,
        int $replacementTargetMajor,
        DateTimeInterface|string|null $asOf = null,
    ): bool {
        if (count($this->paths) < $this->maxPathCount
            || $replacementTargetMajor !== $replacementSourceMajor + 1
            || $replacementSourceMajor !== $this->maxTargetMajor()
            || in_array($replacementSourceMajor, $this->sourceMajors(), true)
        ) {
            return false;
        }

        $date = $this->date($asOf);

        return $date->format('Y-m-d') > $this->oldestSourceSecurityFixUntil();
    }

    /** @return array{phpMinimum: string, securityFixUntil: string} */
    private function sourceEntry(int $major): array
    {
        if (! isset($this->sources[$major])) {
            throw new InvalidArgumentException(sprintf('Laravel source major %d is not in the support policy.', $major));
        }

        return $this->sources[$major];
    }

    private function date(DateTimeInterface|string|null $date): DateTimeImmutable
    {
        if ($date instanceof DateTimeInterface) {
            return new DateTimeImmutable($date->format('Y-m-d'), new DateTimeZone('UTC'));
        }

        if ($date === null) {
            return new DateTimeImmutable('today', new DateTimeZone('UTC'));
        }

        self::assertDate($date, null);

        return new DateTimeImmutable($date, new DateTimeZone('UTC'));
    }

    /** @param array<mixed> $value */
    private static function assertObject(array $value, string $description): void
    {
        if (! self::isObject($value)) {
            throw new InvalidArgumentException(sprintf('%s must be a JSON object.', $description));
        }
    }

    /** @param array<mixed> $value */
    private static function isObject(array $value): bool
    {
        return ! array_is_list($value);
    }

    /** @param array<mixed, mixed> $value
     * @param  list<string>  $allowed
     */
    private static function assertKeys(array $value, array $allowed, string $description): void
    {
        foreach (array_keys($value) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException(sprintf('%s contains an unsupported field "%s".', $description, (string) $key));
            }
        }

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $value)) {
                throw new InvalidArgumentException(sprintf('%s is missing required field "%s".', $description, $key));
            }
        }
    }

    private static function assertStablePhpVersion(mixed $version, int $major): string
    {
        if (! is_string($version) || preg_match('/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)$/', $version) !== 1) {
            throw new InvalidArgumentException(sprintf('Support policy source %d has an invalid stable PHP minimum.', $major));
        }

        return $version;
    }

    private static function assertDate(mixed $date, ?int $major): string
    {
        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException($major === null
                ? 'Support policy retirement date must be a real YYYY-MM-DD date.'
                : sprintf('Support policy source %d has an invalid security-fix date.', $major));
        }

        $validatedDate = $date;

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        if ($parsed === false || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0)) || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException($major === null
                ? 'Support policy retirement date must be a real YYYY-MM-DD date.'
                : sprintf('Support policy source %d has an invalid security-fix date.', $major));
        }

        return $validatedDate;
    }
}
