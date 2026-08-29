<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Release;

use DateTimeImmutable;
use MuhammadSadeeq\LaravelUpgradesRector\PackageInfo;
use Symfony\Component\Process\Process;

/**
 * Validates the repository state required for a package release.
 *
 * This intentionally performs local checks only. It never contacts a package
 * registry, pushes a tag, or publishes an artifact.
 */
final class ReleaseValidator
{
    private const REPOSITORY_URL = 'https://github.com/muhammadsadeeq/laravel-upgrades-rector';

    private const SEMVER_PATTERN = '/^(0|[1-9]\\d*)\\.(0|[1-9]\\d*)\\.(0|[1-9]\\d*)(?:-((?:0|[1-9]\\d*|[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*)(?:\\.(?:0|[1-9]\\d*|[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*))*))?(?:\\+([0-9A-Za-z-]+(?:\\.[0-9A-Za-z-]+)*))?$/D';

    private readonly string $version;

    public function __construct(
        private readonly string $root,
        ?string $version = null,
    ) {
        $this->version = $version ?? PackageInfo::VERSION;
    }

    public static function isValidSemver(string $version): bool
    {
        return preg_match(self::SEMVER_PATTERN, $version) === 1;
    }

    /**
     * @return list<string> Validation errors; an empty list means success.
     */
    public function validate(?string $tagOverride = null): array
    {
        $errors = [];
        $version = $this->version;

        if (! self::isValidSemver($version)) {
            $errors[] = sprintf('Canonical version "%s" is not a valid SemVer release.', $version);
        }

        $composer = $this->jsonFile('composer.json', $errors);

        if ($composer !== null && ($composer['name'] ?? null) !== PackageInfo::NAME) {
            $errors[] = sprintf(
                'composer.json name must be "%s" (found "%s").',
                PackageInfo::NAME,
                is_scalar($composer['name'] ?? null) ? (string) $composer['name'] : 'missing',
            );
        }

        $changelog = $this->textFile('CHANGELOG.md', $errors);

        if ($changelog !== null) {
            $this->validateChangelog($changelog, $version, $errors);
        }

        $tag = $tagOverride ?? $this->releaseTag();

        if ($tag !== null) {
            $this->validateTag($tag, $version, $errors);
        }

        return $errors;
    }

    /** @param list<string> $errors */
    private function validateChangelog(string $changelog, string $version, array &$errors): void
    {
        $currentHeading = '/^## \['.preg_quote($version, '/').'\]\s+—\s+(\d{4}-\d{2}-\d{2})$/m';
        $headingMatches = [];

        if (preg_match($currentHeading, $changelog, $headingMatches) !== 1) {
            $errors[] = sprintf('CHANGELOG.md is missing an exact dated "## [%s]" release entry.', $version);
        } elseif (! $this->isValidCalendarDate($headingMatches[1])) {
            $errors[] = sprintf(
                'CHANGELOG.md release [%s] must use a valid YYYY-MM-DD calendar date (found "%s").',
                $version,
                $headingMatches[1],
            );
        }

        $unreleasedPosition = strpos($changelog, '## [Unreleased]');
        $currentPosition = strpos($changelog, '## ['.$version.']');

        if ($unreleasedPosition === false) {
            $errors[] = 'CHANGELOG.md is missing the "## [Unreleased]" section.';
        } elseif ($currentPosition !== false && $unreleasedPosition > $currentPosition) {
            $errors[] = 'CHANGELOG.md must place [Unreleased] before the latest release.';
        }

        $unreleasedLink = $this->changelogReference($changelog, 'Unreleased');
        $expectedUnreleased = self::REPOSITORY_URL.'/compare/v'.$version.'...HEAD';

        if ($unreleasedLink !== $expectedUnreleased) {
            $errors[] = sprintf(
                'CHANGELOG.md [Unreleased] link must be "%s".',
                $expectedUnreleased,
            );
        }

        $releaseLink = $this->changelogReference($changelog, $version);
        $previous = $this->previousVersion($changelog, $version);

        if ($previous === null) {
            $errors[] = sprintf('CHANGELOG.md has no previous release before %s.', $version);
        } else {
            $expectedRelease = self::REPOSITORY_URL.'/compare/v'.$previous.'...v'.$version;

            if ($releaseLink !== $expectedRelease) {
                $errors[] = sprintf(
                    'CHANGELOG.md [%s] link must be "%s".',
                    $version,
                    $expectedRelease,
                );
            }
        }
    }

    /** @param list<string> $errors */
    private function validateTag(string $tag, string $version, array &$errors): void
    {
        $expectedTag = 'v'.$version;

        if ($tag !== $expectedTag) {
            $errors[] = sprintf('Release tag must be "%s" (found "%s").', $expectedTag, $tag);

            return;
        }

        $objectType = $this->git(['cat-file', '-t', $tag]);

        if ($objectType !== 'tag') {
            $errors[] = sprintf('Release tag "%s" must be an annotated tag created with git tag -a.', $tag);

            return;
        }

        $tagCommit = $this->git(['rev-parse', $tag.'^{}']);
        $head = $this->git(['rev-parse', 'HEAD']);

        if ($tagCommit === null || $head === null || $tagCommit !== $head) {
            $errors[] = sprintf('Release tag "%s" must point at HEAD.', $tag);
        }
    }

    private function releaseTag(): ?string
    {
        $environmentTag = getenv('GITHUB_REF');

        if (is_string($environmentTag) && str_starts_with($environmentTag, 'refs/tags/')) {
            return substr($environmentTag, strlen('refs/tags/'));
        }

        $environmentTag = getenv('CI_COMMIT_TAG');

        if (is_string($environmentTag) && $environmentTag !== '') {
            return $environmentTag;
        }

        if (! $this->isGitRepository()) {
            return null;
        }

        return $this->git(['describe', '--tags', '--exact-match', 'HEAD']);
    }

    private function isGitRepository(): bool
    {
        return is_dir($this->root.'/.git') || is_file($this->root.'/.git');
    }

    /**
     * @param  list<string>  $errors
     * @return array<string, mixed>|null
     */
    private function jsonFile(string $relativePath, array &$errors): ?array
    {
        $path = $this->root.'/'.$relativePath;
        $contents = is_file($path) ? file_get_contents($path) : false;

        if (! is_string($contents)) {
            $errors[] = sprintf('%s is missing.', $relativePath);

            return null;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            $errors[] = sprintf('%s is not valid JSON.', $relativePath);

            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** @param list<string> $errors */
    private function textFile(string $relativePath, array &$errors): ?string
    {
        $contents = is_file($this->root.'/'.$relativePath)
            ? file_get_contents($this->root.'/'.$relativePath)
            : false;

        if (! is_string($contents)) {
            $errors[] = sprintf('%s is missing.', $relativePath);

            return null;
        }

        return $contents;
    }

    private function changelogReference(string $changelog, string $label): ?string
    {
        $pattern = '/^\['.preg_quote($label, '/').'\]:\s*(\S+)\s*$/m';

        return preg_match($pattern, $changelog, $matches) === 1 ? $matches[1] : null;
    }

    private function previousVersion(string $changelog, string $version): ?string
    {
        preg_match_all('/^## \[([^]\r\n]+)\]\s+—\s+\d{4}-\d{2}-\d{2}$/m', $changelog, $matches);
        $versions = array_values(array_filter(
            $matches[1],
            static fn (string $candidate): bool => self::isValidSemver($candidate)
                && version_compare($candidate, $version, '<'),
        ));

        usort($versions, static fn (string $left, string $right): int => version_compare($right, $left));

        return $versions[0] ?? null;
    }

    private function isValidCalendarDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        return $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsed->format('Y-m-d') === $date;
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): ?string
    {
        $process = new Process(array_merge(['git'], $arguments), $this->root);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());

        return $output === '' ? null : $output;
    }
}
