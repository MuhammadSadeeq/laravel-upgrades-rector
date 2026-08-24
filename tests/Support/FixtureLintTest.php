<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Support;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Plan P1-03: static hygiene rules for every rule fixture.
 *
 * - every *.php.inc declares a namespace (global-namespace fixtures hid the
 *   relative-name bug once already);
 * - positive fixtures (with a ----- separator) must have before ≠ after;
 * - fixtures named skip_* must have NO separator (they assert no change);
 * - advisory markers (@laravel-upgrade ...) never appear in a before half
 *   unless the file name starts with skip_already (dedupe paths are covered
 *   by dedicated already-commented fixtures);
 * - no stray extensionless files inside Fixture/ directories.
 */
final class FixtureLintTest extends TestCase
{
    private const MARKER_PREFIX = '@laravel-upgrade ';

    public function test_fixture_directory_hygiene(): void
    {
        $violations = [];
        $fixtureCount = 0;

        foreach ($this->fixtureDirectories() as $directory) {
            foreach ($this->fixtureFiles($directory) as $fileInfo) {
                if (! str_ends_with($fileInfo->getFilename(), '.php.inc')) {
                    $violations[] = sprintf(
                        '%s: stray "%s" — Fixture directories hold only .php.inc files',
                        $this->relative($directory),
                        $fileInfo->getFilename()
                    );

                    continue;
                }

                $fixtureCount++;
                $this->lintFixture($fileInfo, $violations);
            }
        }

        self::assertGreaterThan(100, $fixtureCount, 'Suspiciously few fixtures discovered.');

        self::assertSame(
            [],
            $violations,
            "Fixture hygiene violations:\n".implode("\n", $violations)
        );
    }

    /**
     * @return list<string>
     */
    private function fixtureDirectories(): array
    {
        $directories = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__.'/../../tests/Rector'),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if ($fileInfo->isDir() && $fileInfo->getFilename() === 'Fixture') {
                $directories[] = $fileInfo->getPathname();
            }
        }

        sort($directories);

        return $directories;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function fixtureFiles(string $directory): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if ($fileInfo->isFile()) {
                $files[] = $fileInfo;
            }
        }

        usort($files, static fn (SplFileInfo $a, SplFileInfo $b): int => strcmp($a->getPathname(), $b->getPathname()));

        return $files;
    }

    /**
     * @param  list<string>  $violations
     */
    private function lintFixture(SplFileInfo $file, array &$violations): void
    {
        $contents = (string) file_get_contents($file->getPathname());
        $name = $file->getFilename();
        $where = $this->relative($file->getPath());

        if (! str_contains($contents, '<?php')) {
            $violations[] = "$where/$name: missing <?php opening tag";

            return;
        }

        $namespaced = preg_match('/^namespace\s+\w/m', $contents) === 1;

        if (! $namespaced) {
            $violations[] = "$where/$name: declares no namespace";
        }

        $hasSeparator = str_contains($contents, '-----');
        $isSkipNamed = str_starts_with($name, 'skip_');

        if (! $hasSeparator) {
            if (! $isSkipNamed) {
                $violations[] = "$where/$name: separator-less fixture must start with skip_";
            }

            return;
        }

        if ($isSkipNamed && ! str_starts_with($name, 'skip_already')) {
            // A plain skip_* fixture asserts no change: keep it separator-less.
            $violations[] = "$where/$name: skip_* fixture must not carry an expected half";

            return;
        }

        [$before, $after] = explode('-----', $contents, 2);

        if (rtrim(trim($before)) === rtrim(trim($after))) {
            $violations[] = "$where/$name: before and after halves are identical";
        }

        if (! str_starts_with($name, 'skip_already')
            && str_contains($before, self::MARKER_PREFIX)) {
            $violations[] = "$where/$name: before half already carries an @laravel-upgrade advisory marker";
        }
    }

    private function relative(string $path): string
    {
        return str_replace(dirname(__DIR__, 2).'/', '', $path);
    }
}
