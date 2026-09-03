<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Rector\Carbon3;

use PHPUnit\Framework\TestCase;

/**
 * Proves the carbon-3 set registers its rules ONLY when nesbot/carbon 3 is
 * installed in the analysed project (decision D5). Runs the real rector
 * binary twice against identical fixtures in two synthetic projects.
 */
final class CarbonSetGatingTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        $composerBinary = trim((string) shell_exec('command -v composer'));

        if ($composerBinary === '') {
            self::markTestSkipped('No composer binary available on PATH.');
        }

        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_rules_apply_only_when_carbon_three_is_installed(): void
    {
        // CarbonImmutable is the probe because Carbon 3 defines startOfTime()
        // only there; a mutable receiver is deliberately never rewritten.
        $fixture = <<<'PHP'
<?php

use Carbon\CarbonImmutable;

$min = CarbonImmutable::minValue();
PHP;

        $withoutCarbon = $this->runInSyntheticProject($fixture, null);
        $withCarbonThree = $this->runInSyntheticProject($fixture, '3.8.4.0');
        $withCarbonTwo = $this->runInSyntheticProject($fixture, '2.72.6.0');

        self::assertStringNotContainsString(
            'startOfTime',
            $withoutCarbon,
            'Without resolvable Carbon the rules must not register.'
        );
        self::assertStringContainsString(
            'startOfTime',
            $withCarbonThree,
            'With Carbon 3 installed the rules must apply.'
        );
        self::assertStringNotContainsString(
            'startOfTime',
            $withCarbonTwo,
            'With Carbon 2 installed the rules must not apply.'
        );
    }

    /**
     * @return string processed file contents
     */
    private function runInSyntheticProject(string $fixtureContents, ?string $carbonVersion): string
    {
        $projectDir = sys_get_temp_dir().'/carbon-gate-'.uniqid('', true);
        mkdir($projectDir.'/vendor/composer', 0777, true);

        if ($carbonVersion !== null) {
            file_put_contents($projectDir.'/vendor/composer/installed.json', (string) json_encode([
                'packages' => [
                    ['name' => 'nesbot/carbon', 'version' => 'v'.$carbonVersion, 'version_normalized' => $carbonVersion],
                ],
            ]));
        }

        $fixturePath = $projectDir.'/fixture.php';
        file_put_contents($fixturePath, $fixtureContents);

        $command = sprintf(
            'cd %s && %s/vendor/bin/rector process %s --config=%s/src/Set/carbon-3.php --no-progress-bar --clear-cache --debug 2>&1',
            escapeshellarg($projectDir),
            escapeshellarg($this->repoRoot),
            escapeshellarg($fixturePath),
            escapeshellarg($this->repoRoot)
        );

        $output = [];
        exec($command, $output, $exitCode);

        $contents = (string) file_get_contents($fixturePath);

        // clean up
        @unlink($fixturePath);
        @unlink($projectDir.'/vendor/composer/installed.json');
        @rmdir($projectDir.'/vendor/composer');
        @rmdir($projectDir.'/vendor');
        @rmdir($projectDir);

        self::assertSame(0, $exitCode, "Rector must exit cleanly.\n".implode("\n", $output));

        return $contents;
    }
}
