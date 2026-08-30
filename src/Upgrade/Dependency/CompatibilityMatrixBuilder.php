<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use Composer\Semver\Comparator;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\Intervals;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\SafePath;
use RuntimeException;
use Throwable;

/**
 * Rebuilds the checked-in Laravel package compatibility table from Packagist.
 * Only the existing numeric Laravel-major keys are changed; package metadata
 * and insertion order are retained. This makes adding a new major an explicit
 * data change instead of an accidental side effect of a refresh.
 */
final class CompatibilityMatrixBuilder
{
    private const MAX_JSON_BYTES = 4_194_304;

    /** @var list<string> */
    private const LARAVEL_REQUIREMENTS = [
        'illuminate/support',
        'illuminate/contracts',
        'illuminate/database',
        'laravel/framework',
    ];

    private readonly string $packagesJsonPath;

    private readonly string $phpJsonPath;

    private readonly PackagistClient $client;

    public function __construct(string $packagesJsonPath, string $phpJsonPath, ?PackagistClient $client = null)
    {
        $this->packagesJsonPath = $this->absolutePath($packagesJsonPath, 'packages matrix');
        $this->phpJsonPath = $this->absolutePath($phpJsonPath, 'PHP compatibility data');
        $this->client = $client ?? new PackagistClient;
    }

    /**
     * Build a candidate document. The returned structure is ready to pass to
     * render() or write(); it is never persisted by this method.
     *
     * @return array<string, mixed>
     */
    public function build(?DateTimeInterface $now = null): array
    {
        $document = $this->readJson($this->packagesJsonPath, 'packages matrix');
        $phpDocument = $this->readJson($this->phpJsonPath, 'PHP compatibility data');
        $packages = $document['packages'] ?? null;

        if (! is_array($packages) || ! $this->isAssociative($packages)) {
            throw new RuntimeException('The packages matrix must contain a non-empty object in its "packages" section.');
        }

        $packages = $this->packageEntries($packages, 'packages matrix');

        $phpValues = $phpDocument['php'] ?? null;

        if (! is_array($phpValues)) {
            throw new RuntimeException('The PHP compatibility data is missing its "php" section.');
        }

        $phpValues = $this->packageEntries($phpValues, 'PHP compatibility data');

        $updatedPackages = [];
        $hasDataChange = false;

        foreach ($packages as $package => $entry) {
            $updated = $entry;
            $targetKeys = $this->targetKeys($entry);

            if ($package === 'php') {
                foreach ($targetKeys as $major) {
                    $phpEntry = $phpValues[$major] ?? null;
                    $minimum = is_array($phpEntry) ? ($phpEntry['minimum'] ?? null) : null;

                    if (! is_string($minimum) || $minimum === '') {
                        throw new RuntimeException(sprintf('PHP compatibility data has no minimum for Laravel %s.', $major));
                    }

                    $this->assertVersion($minimum, sprintf('PHP Laravel %s minimum', $major));
                    $updated[$major] = $this->compactVersion((new VersionParser)->normalize($minimum));
                }
            } elseif ($package === 'laravel/framework') {
                foreach ($targetKeys as $major) {
                    $updated[$major] = $major.'.0.0';
                }
            } else {
                $releases = $this->client->releases($package);
                $evaluated = $this->evaluateReleases($package, $releases);

                foreach ($targetKeys as $major) {
                    $minimum = $this->minimumForMajor($package, $evaluated, $major);

                    if ($minimum === null) {
                        // A few development tools in the seed table (notably
                        // PHPUnit/Pest) do not declare Illuminate at all. No
                        // constraint can prove a better floor for those, so
                        // retain their reviewed seed value. Packages that do
                        // declare a Laravel constraint and have no match fail
                        // loudly below.
                        if ($evaluated['hasLaravelRequirement']) {
                            throw new RuntimeException(sprintf('Package "%s" has no stable release compatible with Laravel %s.', $package, $major));
                        }

                        continue;
                    }

                    $updated[$major] = $minimum;
                }
            }

            if ($updated !== $entry) {
                $hasDataChange = true;
            }

            $updatedPackages[$package] = $updated;
        }

        $document['packages'] = $updatedPackages;

        $generatedAt = $document['generatedAt'] ?? null;

        if (array_key_exists('generatedAt', $document) && (! is_string($generatedAt) || ! $this->isCalendarDate($generatedAt))) {
            throw new RuntimeException('The compatibility matrix generatedAt must be a valid YYYY-MM-DD calendar date.');
        }

        if ($hasDataChange || ! array_key_exists('generatedAt', $document)) {
            $document['generatedAt'] = ($now ?? new DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        }

        return $document;
    }

    /**
     * Render with stable flags and a trailing newline. Package entries use
     * compact JSON to retain the compact style of the checked-in seed while
     * preserving all metadata keys and their original order.
     *
     * @param  array<string, mixed>  $document
     */
    public function render(array $document): string
    {
        $lines = ['{'];
        $topLevel = array_keys($document);

        foreach ($topLevel as $index => $key) {
            $value = $document[$key];
            $comma = $index === count($topLevel) - 1 ? '' : ',';
            $encodedKey = $this->encode($key);

            if ($key === 'packages' && is_array($value)) {
                $lines[] = '    '.$encodedKey.': {';
                $packageKeys = array_keys($value);

                foreach ($packageKeys as $packageIndex => $packageKey) {
                    $packageComma = $packageIndex === count($packageKeys) - 1 ? '' : ',';
                    $packageValue = $value[$packageKey];

                    if (! is_array($packageValue)) {
                        throw new RuntimeException(sprintf('The packages matrix entry "%s" must be an object.', (string) $packageKey));
                    }

                    $lines[] = '        '.$this->encode((string) $packageKey).': '.$this->inlineEncode($packageValue).$packageComma;
                }

                $lines[] = '    }'.$comma;

                continue;
            }

            $valueLines = explode("\n", $this->encode($value));
            $lines[] = '    '.$encodedKey.': '.array_shift($valueLines);

            foreach ($valueLines as $valueLine) {
                $lines[] = '    '.$valueLine;
            }

            $lines[array_key_last($lines)] .= $comma;
        }

        $lines[] = '}';

        return implode("\n", $lines).PHP_EOL;
    }

    private function encode(mixed $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not encode the compatibility matrix: '.$exception->getMessage(), 0, $exception);
        }
    }

    /** @param array<mixed, mixed> $value */
    private function inlineEncode(array $value): string
    {
        $encoded = $this->encode($value);
        $inline = '';
        $quoted = false;
        $escaped = false;

        foreach (str_split($encoded) as $character) {
            if ($quoted) {
                $inline .= $character;

                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $quoted = false;
                }

                continue;
            }

            if ($character === '"') {
                $quoted = true;
                $inline .= $character;
            } elseif ($character === ':' || $character === ',') {
                $inline .= $character.' ';
            } elseif ($character !== "\n" && $character !== "\r" && $character !== "\t" && $character !== ' ') {
                $inline .= $character;
            }
        }

        if (str_starts_with($inline, '{') && str_ends_with($inline, '}') && strlen($inline) > 2) {
            return '{ '.substr($inline, 1, -1).' }';
        }

        return $inline;
    }

    /**
     * Atomically persist a candidate and return whether bytes changed. The
     * destination and every existing parent component are checked for
     * symlinks before creating a temporary sibling and again before rename.
     * These checks reduce accidental traversal; portable PHP cannot make the
     * final rename descriptor-relative or eliminate a concurrent TOCTOU race.
     *
     * @param  array<string, mixed>  $document
     */
    public function write(array $document): bool
    {
        SafePath::assertNoSymlinkComponents($this->packagesJsonPath, 'compatibility matrix path');
        $directory = dirname($this->packagesJsonPath);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create compatibility matrix directory "%s".', $directory));
        }

        SafePath::assertNoSymlinkComponents($directory, 'compatibility matrix directory');
        $resolvedDirectory = realpath($directory);

        if ($resolvedDirectory === false || ! is_dir($resolvedDirectory)) {
            throw new RuntimeException(sprintf('Could not resolve compatibility matrix directory "%s".', $directory));
        }

        $destination = $resolvedDirectory.'/'.basename($this->packagesJsonPath);
        SafePath::assertNoSymlinkComponents($destination, 'compatibility matrix path');
        $contents = $this->render($document);
        $existing = is_file($destination) ? file_get_contents($destination) : false;

        if ($existing === $contents) {
            return false;
        }

        $temporary = tempnam($resolvedDirectory, '.compat-matrix-');

        if ($temporary === false) {
            throw new RuntimeException('Could not create a temporary compatibility matrix file.');
        }

        $mode = is_file($destination) ? fileperms($destination) : 0644;

        if ($mode === false) {
            throw new RuntimeException(sprintf('Could not determine compatibility matrix mode "%s".', $destination));
        }

        $mode &= 0777;

        try {
            SafePath::assertNoSymlinkComponents($destination, 'compatibility matrix path');
            SafePath::assertNoSymlinkComponents($temporary, 'temporary compatibility matrix path');
            $written = file_put_contents($temporary, $contents, LOCK_EX);

            SafePath::assertNoSymlinkComponents($destination, 'compatibility matrix path');
            SafePath::assertNoSymlinkComponents($temporary, 'temporary compatibility matrix path');

            if ($written === false || $written !== strlen($contents) || ! chmod($temporary, $mode) || ! rename($temporary, $destination)) {
                throw new RuntimeException(sprintf('Could not atomically write compatibility matrix "%s".', $this->packagesJsonPath));
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     * @return array{releases: list<array{version: string, requirements: array<string, string>}>, hasLaravelRequirement: bool}
     */
    private function evaluateReleases(string $package, array $releases): array
    {
        /** @var list<array{version: string, requirements: array<string, string>}> $evaluated */
        $evaluated = [];
        $hasLaravelRequirement = false;

        foreach ($releases as $release) {
            $version = $this->releaseVersion($package, $release);
            $versionLabel = $version ?? '(unstable release)';
            $require = array_key_exists('require', $release) ? $release['require'] : [];

            if (! is_array($require)) {
                throw new RuntimeException(sprintf('Packagist release "%s" for "%s" has a malformed require section.', $versionLabel, $package));
            }

            if ($require !== [] && array_is_list($require)) {
                throw new RuntimeException(sprintf('Packagist release "%s" for "%s" has a malformed require section.', $versionLabel, $package));
            }

            foreach ($require as $requireName => $requirement) {
                if (! is_string($requireName) || ! is_string($requirement) || trim($requirement) === '') {
                    throw new RuntimeException(sprintf('Packagist release "%s" for "%s" has a malformed require entry.', $versionLabel, $package));
                }
            }

            /** @var array<string, string> $requirements */
            $requirements = [];
            $selfVersion = false;

            foreach (self::LARAVEL_REQUIREMENTS as $name) {
                if (! array_key_exists($name, $require)) {
                    continue;
                }

                $constraint = $require[$name];

                if (trim($constraint) === '') {
                    throw new RuntimeException(sprintf('Packagist release "%s" for "%s" has a malformed %s constraint.', $versionLabel, $package, $name));
                }

                if (strtolower(trim($constraint)) === 'self.version') {
                    // Composer resolves this token against the package being
                    // published, not against Laravel. It cannot establish a
                    // Laravel-major floor, so discard this release while
                    // retaining the fact that a Laravel requirement existed.
                    $selfVersion = true;
                    $hasLaravelRequirement = true;

                    continue;
                }

                // Parse once here so a malformed constraint is an operational
                // failure, not an apparently compatible omission.
                try {
                    Semver::satisfies('1.0.0', $constraint);
                } catch (Throwable $exception) {
                    throw new RuntimeException(sprintf('Packagist release "%s" for "%s" has an invalid %s constraint: %s', $versionLabel, $package, $name, $exception->getMessage()), 0, $exception);
                }

                $requirements[$name] = trim($constraint);
                $hasLaravelRequirement = true;
            }

            if ($version === null || $selfVersion) {
                continue;
            }

            $evaluated[] = ['version' => $version, 'requirements' => $requirements];
        }

        usort($evaluated, static function (array $left, array $right): int {
            if ($left['version'] === $right['version']) {
                return strcmp(json_encode($left['requirements']) ?: '', json_encode($right['requirements']) ?: '');
            }

            return Comparator::lessThan($left['version'], $right['version']) ? -1 : 1;
        });

        return [
            'releases' => $evaluated,
            'hasLaravelRequirement' => $hasLaravelRequirement,
        ];
    }

    /**
     * @param  array{releases: list<array{version: string, requirements: array<string, string>}>, hasLaravelRequirement: bool}  $evaluated
     */
    private function minimumForMajor(string $package, array $evaluated, string $major): ?string
    {
        $nextMajor = (string) ((int) $major + 1);
        $majorLine = (new VersionParser)->parseConstraints(sprintf('>=%s.0.0 <%s.0.0', $major, $nextMajor));

        foreach ($evaluated['releases'] as $release) {
            if ($release['requirements'] === []) {
                continue;
            }

            $constraints = [$majorLine];

            foreach ($release['requirements'] as $name => $constraint) {
                try {
                    $constraints[] = (new VersionParser)->parseConstraints($constraint);
                } catch (Throwable $exception) {
                    throw new RuntimeException(sprintf('Could not evaluate %s requirement "%s" for "%s".', $name, $constraint, $package), 0, $exception);
                }
            }

            $combined = new MultiConstraint($constraints, true);

            if (Intervals::haveIntersections($combined, $majorLine)) {
                return $release['version'];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $release */
    private function releaseVersion(string $package, array $release): ?string
    {
        $raw = is_string($release['version_normalized'] ?? null) && $release['version_normalized'] !== ''
            ? $release['version_normalized']
            : ($release['version'] ?? null);

        if (! is_string($raw) || trim($raw) === '') {
            throw new RuntimeException(sprintf('Packagist release for "%s" has no version.', $package));
        }

        $raw = trim($raw);

        try {
            if (VersionParser::parseStability($raw) !== 'stable') {
                // Development and pre-release records are valid Packagist
                // records, but intentionally do not participate in floors.
                return null;
            }

            $normalized = (new VersionParser)->normalize($raw);

            if (VersionParser::parseStability($normalized) !== 'stable') {
                return null;
            }
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('Packagist release "%s" has an invalid version for "%s": %s', $raw, $package, $exception->getMessage()), 0, $exception);
        }

        return $this->compactVersion($normalized);
    }

    private function compactVersion(string $normalized): string
    {
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)\.0$/', $normalized, $matches) === 1) {
            return $matches[1].'.'.$matches[2].'.'.$matches[3];
        }

        return $normalized;
    }

    private function assertVersion(string $version, string $description): void
    {
        try {
            $normalized = (new VersionParser)->normalize($version);

            if (VersionParser::parseStability($normalized) !== 'stable') {
                throw new RuntimeException(sprintf('%s must be stable.', $description));
            }
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException && str_contains($exception->getMessage(), 'must be stable')) {
                throw $exception;
            }

            throw new RuntimeException(sprintf('%s is not a valid Composer version: %s', $description, $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    private function targetKeys(array $entry): array
    {
        /** @var list<string> $keys */
        $keys = [];

        foreach (array_keys($entry) as $key) {
            if (preg_match('/^[1-9]\d*$/', (string) $key) === 1) {
                $keys[] = (string) $key;
            }
        }

        usort($keys, static fn (string $left, string $right): int => (int) $left <=> (int) $right);

        return $keys;
    }

    /**
     * @param  array<string|int, mixed>  $value
     * @return array<string, array<string, mixed>>
     */
    private function packageEntries(array $value, string $description): array
    {
        /** @var array<string, array<string, mixed>> $entries */
        $entries = [];

        foreach ($value as $key => $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException(sprintf('The %s contains a malformed entry.', $description));
            }

            $packageKey = (string) $key;

            /** @var array<string, mixed> $normalised */
            $normalised = [];

            foreach ($entry as $entryKey => $item) {
                $normalised[(string) $entryKey] = $item;
            }

            $entries[$packageKey] = $normalised;
        }

        return $entries;
    }

    /** @param array<mixed, mixed> $value */
    private function isAssociative(array $value): bool
    {
        return $value !== [] && ! array_is_list($value);
    }

    private function absolutePath(string $path, string $description): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException(sprintf('Unsafe %s path "%s".', $description, $path));
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        if (preg_match('~(^|/)\.\.(/|$)~', $path) === 1) {
            throw new RuntimeException(sprintf('Unsafe %s path "%s".', $description, $path));
        }

        $workingDirectory = getcwd();

        if ($workingDirectory === false) {
            throw new RuntimeException(sprintf('Could not resolve the working directory for %s path.', $description));
        }

        return $workingDirectory.'/'.$path;
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

    /** @return array<string, mixed> */
    private function readJson(string $path, string $description): array
    {
        SafePath::assertNoSymlinkComponents($path, $description.' path');

        if (! is_file($path)) {
            throw new RuntimeException(sprintf('%s "%s" was not found.', $description, $path));
        }

        $size = filesize($path);

        if ($size === false || $size > self::MAX_JSON_BYTES) {
            throw new RuntimeException(sprintf('%s "%s" exceeds the %d-byte limit.', $description, $path, self::MAX_JSON_BYTES));
        }

        SafePath::assertNoSymlinkComponents($path, $description.' path');
        $raw = file_get_contents($path);

        if ($raw === false || $raw === '') {
            throw new RuntimeException(sprintf('%s "%s" is empty or unreadable.', $description, $path));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('%s "%s" contains invalid JSON: %s', $description, $path, $exception->getMessage()), 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('%s "%s" must contain a JSON object.', $description, $path));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
