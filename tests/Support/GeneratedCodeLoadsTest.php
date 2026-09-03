<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Generated-code load gate.
 *
 * Rector fixture AFTER halves are class definitions that claim to satisfy
 * real framework contracts. A fixture whose expected output omits (or
 * mismatches) an interface method parses fine and passes every AST-level
 * assertion, yet explodes the moment the generated code is actually loaded.
 *
 * This gate closes that hole: every fixture marked with a first-line
 * `@loads` marker has its AFTER half executed by a fresh PHP process
 * against the REAL vendor tree of the environment its suite targets.
 * The negative test proves the harness can actually fail by feeding it a
 * deliberately incompatible implementation.
 */
final class GeneratedCodeLoadsTest extends TestCase
{
    private const MARKER = '@loads';

    /**
     * Suite namespace segment => LARAVEL_ENV value whose vendor tree the
     * fixture's AFTER half must load against. Mirrors
     * AbstractUpgradeRectorTestCase::ENV_BY_NAMESPACE_SEGMENT.
     *
     * @var array<string, string>
     */
    private const ENV_BY_NAMESPACE_SEGMENT = [
        'Laravel11' => '11',
        'Laravel12' => '12',
        'Carbon3' => '12',
        'Laravel13' => '13',
    ];

    public function test_marked_fixture_outputs_load_against_real_framework(): void
    {
        if (EnvAutoload::vendorDirectory() === null) {
            self::markTestSkipped('No LARAVEL_ENV set — the load gate needs a real framework vendor tree.');
        }

        $fixtures = $this->markedFixtures();

        self::assertGreaterThan(
            0,
            count($fixtures),
            'No @loads-marked fixtures discovered — the glob is broken or markers were dropped.'
        );

        $failures = [];

        foreach ($fixtures as $fixture) {
            try {
                $this->assertAfterHalfLoads($fixture, $this->expectedEnvFor($fixture));
            } catch (\Throwable $throwable) {
                $failures[] = $throwable->getMessage();
            }
        }

        self::assertSame([], $failures, implode("\n\n", $failures));
    }

    public function test_gate_detects_narrowed_signature(): void
    {
        if (EnvAutoload::vendorDirectory() === null) {
            self::markTestSkipped('No LARAVEL_ENV set — the load gate needs a real framework vendor tree.');
        }

        $fixture = __DIR__.'/Fixture/NarrowedSignatureFixture.php.inc';
        self::assertFileExists($fixture);

        // The negative fixture narrows parameter types the interface leaves
        // untyped, so loading MUST abort with a fatal declaration error.
        // If it loads cleanly, the harness itself is broken and every green
        // result above is meaningless.
        $result = $this->loadAfterHalfInFreshProcess($fixture, (string) file_get_contents($fixture));

        self::assertTrue(
            $result['exitCode'] !== 0 || str_contains($result['output'], 'Fatal error'),
            sprintf(
                'The deliberately incompatible NarrowedSignatureFixture LOADED cleanly '
                .'(exit code %d) — the load gate cannot detect broken generated code '
                ."and all positive results are unreliable.\nOutput:\n%s",
                $result['exitCode'],
                $result['output']
            )
        );
    }

    /**
     * Loads the AFTER half of one fixture against the given environment's
     * real vendor autoloader and fails with the process output when the
     * generated code does not load.
     */
    private function assertAfterHalfLoads(string $fixturePath, string $env): void
    {
        $autoloadPath = dirname(__DIR__, 2)
            .'/tests/env/laravel-'.$env.'/vendor/autoload.php';

        if (! is_file($autoloadPath)) {
            self::fail(sprintf('Env vendor autoloader missing: %s', $autoloadPath));
        }

        $result = $this->loadAfterHalfInFreshProcess($fixturePath, $this->afterHalfOf($fixturePath), $autoloadPath);

        $problems = [];

        if ($result['exitCode'] !== 0) {
            $problems[] = sprintf('exited with code %d', $result['exitCode']);
        }

        if (str_contains($result['output'], 'Fatal error')) {
            $problems[] = 'emitted a fatal error';
        }

        if (str_contains($result['output'], 'Declaration of')) {
            $problems[] = 'emitted an incompatible-signature declaration error';
        }

        self::assertSame([], $problems, sprintf(
            "@loads fixture \"%s\" does not load against laravel-%s (%s):\n%s",
            $fixturePath,
            $env,
            implode('; ', $problems),
            $result['output']
        ));
    }

    /**
     * Writes $code to a temp fixture plus a loader that pulls in the env
     * autoloader and then the temp fixture, and executes the loader with a
     * fresh PHP process so nothing leaks into this test run. Both temp files
     * are removed even when the process fails.
     *
     * @return array{exitCode: int, output: string}
     */
    private function loadAfterHalfInFreshProcess(string $fixturePath, string $code, ?string $autoloadPath = null): array
    {
        $autoloadPath ??= EnvAutoload::vendorDirectory().'/autoload.php';

        $hash = md5($fixturePath);
        $tempFixture = sys_get_temp_dir().'/load-gate-'.$hash.'.php.inc';
        $tempLoader = sys_get_temp_dir().'/load-gate-'.$hash.'-loader.php';

        try {
            file_put_contents($tempFixture, $code);
            file_put_contents($tempLoader, sprintf(
                "<?php require '%s'; require '%s';\n",
                str_replace("'", "\\'", $autoloadPath),
                str_replace("'", "\\'", $tempFixture)
            ));

            exec(sprintf(
                'php -d display_errors=1 %s 2>&1',
                escapeshellarg($tempLoader)
            ), $outputLines, $exitCode);

            return [
                'exitCode' => $exitCode,
                'output' => implode("\n", $outputLines),
            ];
        } finally {
            @unlink($tempFixture);
            @unlink($tempLoader);
        }
    }

    /**
     * @return list<string>
     */
    private function markedFixtures(): array
    {
        $fixtures = [];

        foreach (['Laravel11', 'Laravel12', 'Laravel13', 'Carbon3'] as $segment) {
            foreach (glob(__DIR__.'/../Rector/'.$segment.'/*/Fixture/*.php.inc') ?: [] as $fixture) {
                if (str_contains($this->firstLineOf($fixture), self::MARKER)) {
                    $fixtures[] = $fixture;
                }
            }
        }

        sort($fixtures);

        return $fixtures;
    }

    private function afterHalfOf(string $fixturePath): string
    {
        $halves = explode('-----', (string) file_get_contents($fixturePath));

        self::assertArrayHasKey(1, $halves, sprintf('Fixture "%s" has no "-----" separator.', $fixturePath));

        return ltrim(rtrim($halves[1])."\n");
    }

    private function firstLineOf(string $fixturePath): string
    {
        return (string) strtok((string) file_get_contents($fixturePath), "\n");
    }

    private function expectedEnvFor(string $fixturePath): string
    {
        foreach (self::ENV_BY_NAMESPACE_SEGMENT as $segment => $env) {
            if (str_contains($fixturePath, '/Rector/'.$segment.'/')) {
                return $env;
            }
        }

        self::fail(sprintf('Cannot derive an environment for fixture "%s".', $fixturePath));
    }
}
