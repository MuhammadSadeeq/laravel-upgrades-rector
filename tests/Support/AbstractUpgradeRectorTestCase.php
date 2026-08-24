<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Support;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Base test case for upgrade rules. Adds a second-pass idempotency gate to
 * Rector's fixture runner: after the standard before/after assertion, the
 * rule is applied AGAIN to its own expected output and must produce no
 * further change (plan P0-13).
 *
 * A fixture may opt out with a first-line `// @not-idempotent-by-design`
 * comment — expected only for rules that deliberately converge over
 * multiple runs; none should exist.
 */
abstract class AbstractUpgradeRectorTestCase extends AbstractRectorTestCase
{
    private const IDEMPOTENCY_SKIP_MARKER = '@not-idempotent-by-design';

    /**
     * Suite namespace segment => LARAVEL_ENV value expected by its fixtures.
     *
     * @var array<string, string>
     */
    private const ENV_BY_NAMESPACE_SEGMENT = [
        'Laravel11' => '11',
        'Laravel12' => '12',
        'Carbon3' => '12',
        'Laravel13' => '13',
    ];

    protected function doTestFile(string $fixtureFilePath, bool $includeFixtureDirectoryAsSource = false): void
    {
        if (! $this->activeEnvironmentMatches($fixtureFilePath)) {
            self::markTestSkipped(sprintf(
                'Fixture "%s" needs a different LARAVEL_ENV.',
                basename($fixtureFilePath)
            ));
        }

        try {
            parent::doTestFile($fixtureFilePath, $includeFixtureDirectoryAsSource);
        } catch (\Throwable $throwable) {
            throw new \RuntimeException(sprintf(
                'First pass failed for "%s": %s',
                $fixtureFilePath,
                $throwable->getMessage()
            ), 0, $throwable);
        }

        // Rector's tearDown only removes the LAST input file it recorded;
        // our extra second-pass run overwrites that pointer, so the first
        // pass's derived input file must be removed here.
        $firstPassInputPath = str_ends_with($fixtureFilePath, '.inc')
            ? substr($fixtureFilePath, 0, -4)
            : null;

        if ($this->shouldSkipIdempotencyCheck($fixtureFilePath)) {
            if ($firstPassInputPath !== null && getenv('KEEP_IDEMPOTENCY_FIXTURES') !== '1') {
                @unlink($firstPassInputPath);
            }

            return;
        }

        $expectedOnlyPath = $this->createExpectedOnlyFixture($fixtureFilePath);

        if ($expectedOnlyPath === null) {
            // Skip-type fixture: input equals output by definition.
            return;
        }

        try {
            // The second run must not change the already-transformed code.
            parent::doTestFile($expectedOnlyPath, $includeFixtureDirectoryAsSource);
        } finally {
            // Remove our temp fixture AND the derived input file that Rector's
            // own tearDown cannot know about.
            if (getenv('KEEP_IDEMPOTENCY_FIXTURES') !== '1') {
                @unlink($expectedOnlyPath);
                @unlink(substr($expectedOnlyPath, 0, -4));

                if ($firstPassInputPath !== null) {
                    @unlink($firstPassInputPath);
                }
            }
        }
    }

    /**
     * Writes a temporary fixture whose content is ONLY the expected half,
     * so that running the rule again asserts "no change".
     */
    private function createExpectedOnlyFixture(string $fixtureFilePath): ?string
    {
        $contents = (string) file_get_contents($fixtureFilePath);

        if (! str_contains($contents, '-----')) {
            return null;
        }

        $expected = explode('-----', $contents)[1];
        // The ".inc" suffix makes Rector derive a distinct input-file path.
        // Content is trimmed so no inline HTML precedes the open tag.
        $tempPath = sys_get_temp_dir().'/idempotency-'.md5($fixtureFilePath).'.php.inc';
        file_put_contents($tempPath, ltrim(rtrim($expected)."\n"));

        return $tempPath;
    }

    private function shouldSkipIdempotencyCheck(string $fixtureFilePath): bool
    {
        $firstLine = (string) strtok((string) file_get_contents($fixtureFilePath), "\n");

        return str_contains($firstLine, self::IDEMPOTENCY_SKIP_MARKER);
    }

    /**
     * Rule fixtures assert against the behaviour of ONE real framework
     * version, so a suite only runs under its matching LARAVEL_ENV.
     */
    private function activeEnvironmentMatches(string $fixtureFilePath): bool
    {
        $active = getenv('LARAVEL_ENV');

        if (! is_string($active) || $active === '') {
            return false;
        }

        $expected = null;

        foreach (self::ENV_BY_NAMESPACE_SEGMENT as $segment => $env) {
            if (str_contains($fixtureFilePath, '/Rector/'.$segment.'/')) {
                $expected = $env;

                break;
            }
        }

        // Environment-independent suites always run.
        return $expected === null || $expected === $active;
    }
}
