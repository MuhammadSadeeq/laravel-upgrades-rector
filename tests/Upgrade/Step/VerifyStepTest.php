<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\VerifyStep;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VerifyStepTest extends TestCase
{
    private string $projectDirectory;

    protected function setUp(): void
    {
        $this->projectDirectory = sys_get_temp_dir().'/laravel-upgrade-verify-'.bin2hex(random_bytes(8));
        mkdir($this->projectDirectory.'/app', 0777, true);
        mkdir($this->projectDirectory.'/vendor', 0777, true);
        file_put_contents($this->projectDirectory.'/composer.json', '{"name":"verify-test"}');
        file_put_contents($this->projectDirectory.'/vendor/autoload.php', "<?php\n");
        file_put_contents($this->projectDirectory.'/artisan', "<?php\n");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDirectory);
    }

    public function test_plan_is_byte_neutral_and_returns_exact_command_preview(): void
    {
        file_put_contents($this->projectDirectory.'/app/Thing.php', "<?php\nclass Thing {}\n");
        $before = $this->snapshot();
        $runner = new VerifyStepFakeProcessRunner;

        $result = $this->step($runner)->execute($this->context([
            'changedFiles' => ['app/Thing.php'],
        ], true, 13));

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame([], $runner->requests);
        self::assertSame($before, $this->snapshot());
        self::assertDirectoryDoesNotExist($this->projectDirectory.'/.laravel-upgrade');
        $checks = $result->data['checks'] ?? null;
        self::assertIsArray($checks);
        $statuses = array_map(
            static fn (mixed $check): mixed => is_array($check) ? ($check['status'] ?? null) : null,
            $checks,
        );
        self::assertSame(['preview', 'preview', 'preview', 'preview', 'preview', 'preview', 'preview', 'preview', 'skipped'], $statuses);
        self::assertSame('validate', $this->commandAt($checks, 0)[1] ?? null);
        self::assertSame('about', $this->commandAt($checks, 3)[2] ?? null);
        self::assertSame('config:cache', $this->commandAt($checks, 5)[2] ?? null);
        self::assertSame('config:clear', $this->commandAt($checks, 6)[2] ?? null);
    }

    public function test_apply_runs_all_successful_checks_in_order(): void
    {
        file_put_contents($this->projectDirectory.'/app/Thing.php', "<?php\nclass Thing {}\n");
        $runner = new VerifyStepFakeProcessRunner([
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, 'No syntax errors detected'),
            new ProcessResult([], 0, ''),
            new ProcessResult([], 0, '{"environment":"testing"}'),
            new ProcessResult([], 0, '[]'),
            new ProcessResult([], 0, 'cached'),
            new ProcessResult([], 0, 'cleared'),
            new ProcessResult([], 0, 'OK (2 tests, 4 assertions)'),
        ]);

        $result = $this->step($runner)->execute($this->context(['changedFiles' => ['app/Thing.php']], false, 13));

        self::assertTrue($result->isSuccessful(), $result->message.' '.json_encode($result->data));
        self::assertCount(8, $runner->requests);
        self::assertSame('validate', $runner->requests[0]->arguments[1] ?? null);
        self::assertSame('-l', $runner->requests[1]->arguments[1] ?? null);
        self::assertSame('-r', $runner->requests[2]->arguments[1] ?? null);
        self::assertSame('about', $runner->requests[3]->arguments[2] ?? null);
        self::assertSame('route:list', $runner->requests[4]->arguments[2] ?? null);
        self::assertSame('config:cache', $runner->requests[5]->arguments[2] ?? null);
        self::assertSame('config:clear', $runner->requests[6]->arguments[2] ?? null);
        self::assertSame('test', $runner->requests[7]->arguments[2] ?? null);
        self::assertFileExists($this->projectDirectory.'/.laravel-upgrade/findings.jsonl');
        self::assertSame([], $result->data['findings'] ?? null);
        $summary = $this->summaryFromResult($result);
        self::assertSame('passed', $summary['status'] ?? null);
        self::assertSame(2, $summary['tests'] ?? null);
        self::assertSame(4, $summary['assertions'] ?? null);
    }

    public function test_class_loading_ignores_comments_and_strings_and_keeps_real_namespaced_declarations(): void
    {
        file_put_contents(
            $this->projectDirectory.'/app/Real.php',
            "<?php\nnamespace App;\n// class CommentOnly {}\n\$text = 'class StringOnly {}';\ninterface Contract {}\ntrait Helper {}\nenum Status { case Ready; }\nfinal class Real {}\nclass Second {}\n\$anonymous = new class {};\n",
        );
        $runner = new VerifyStepFakeProcessRunner([
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, 'No syntax errors detected'),
            new ProcessResult([], 0, ''),
            new ProcessResult([], 0, '{"environment":"testing"}'),
            new ProcessResult([], 0, 'cached'),
            new ProcessResult([], 0, 'cleared'),
            new ProcessResult([], 0, 'OK (1 test, 1 assertion)'),
        ]);

        $result = $this->step($runner)->execute($this->context(['changedFiles' => ['app/Real.php']], false, 11));

        self::assertTrue($result->isSuccessful(), $result->message);
        $script = $runner->requests[2]->arguments[2] ?? null;
        self::assertIsString($script);
        self::assertStringContainsString(var_export('App\Real', true), $script);
        self::assertStringContainsString(var_export('App\Contract', true), $script);
        self::assertStringContainsString(var_export('App\Helper', true), $script);
        self::assertStringContainsString(var_export('App\Status', true), $script);
        self::assertStringContainsString(var_export('App\Second', true), $script);
        self::assertStringNotContainsString('CommentOnly', $script);
        self::assertStringNotContainsString('StringOnly', $script);
    }

    public function test_syntax_failure_names_the_file_and_finishes_independent_checks(): void
    {
        file_put_contents($this->projectDirectory.'/app/Broken.php', "<?php\nclass Broken {\n");
        $runner = new VerifyStepFakeProcessRunner([
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 1, '', 'Parse error in app/Broken.php'),
            new ProcessResult([], 0, ''),
            new ProcessResult([], 0, '{"environment":"testing"}'),
            new ProcessResult([], 0, '[]'),
            new ProcessResult([], 0, 'cached'),
            new ProcessResult([], 0, 'cleared'),
            new ProcessResult([], 0, 'Tests: 1 passed'),
        ]);

        $result = $this->step($runner)->execute($this->context(['changedFiles' => ['app/Broken.php']], false, 13));

        self::assertTrue($result->isFailed());
        self::assertSame(1, $result->exitCode);
        self::assertSame(8, count($runner->requests));
        $findings = $result->data['findings'] ?? null;
        self::assertIsArray($findings);
        self::assertStringContainsString('app/Broken.php', json_encode($findings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function test_boot_and_test_failures_are_high_and_no_tests_skips_test_command(): void
    {
        $runner = new VerifyStepFakeProcessRunner([
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 1, '', 'Application failed to boot'),
            new ProcessResult([], 0, 'cached'),
            new ProcessResult([], 0, 'cleared'),
        ]);

        $result = $this->step($runner)->execute($this->context(['noTests' => true], false, 11));

        self::assertTrue($result->isFailed());
        self::assertCount(4, $runner->requests);
        self::assertSame('about', $runner->requests[1]->arguments[2] ?? null);
        self::assertSame('config:clear', $runner->requests[3]->arguments[2] ?? null);
        self::assertStringContainsString('high', json_encode($result->data['findings'] ?? [], JSON_THROW_ON_ERROR));
    }

    public function test_route_json_reports_duplicate_names_and_domain_precedence_conflicts(): void
    {
        $routes = json_encode([
            ['domain' => 'api.example.test', 'method' => 'GET', 'uri' => 'users', 'name' => 'users.index'],
            ['domain' => '', 'method' => 'GET', 'uri' => 'users', 'name' => 'users.index'],
        ], JSON_THROW_ON_ERROR);
        $runner = new VerifyStepFakeProcessRunner([
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, '{"environment":"testing"}'),
            new ProcessResult([], 0, $routes),
            new ProcessResult([], 0, 'cached'),
            new ProcessResult([], 0, 'cleared'),
            new ProcessResult([], 0, 'Tests: 1 passed'),
        ]);

        $result = $this->step($runner)->execute($this->context([], false, 13));

        self::assertTrue($result->isFailed());
        self::assertSame(6, count($runner->requests));
        $findings = json_encode($result->data['findings'] ?? [], JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Duplicate route name', $findings);
        self::assertStringContainsString('domain and non-domain', strtolower($findings));
    }

    public function test_config_clear_runs_after_config_cache_failure(): void
    {
        $runner = new VerifyStepFakeProcessRunner([
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, '{"environment":"testing"}'),
            new ProcessResult([], 5, '', 'closure cannot be serialized'),
            new ProcessResult([], 0, 'cleared'),
            new ProcessResult([], 0, 'Tests: 1 passed'),
        ]);

        $result = $this->step($runner)->execute($this->context([], false, 11));

        self::assertTrue($result->isFailed());
        self::assertCount(5, $runner->requests);
        self::assertSame('config:cache', $runner->requests[2]->arguments[2] ?? null);
        self::assertSame('config:clear', $runner->requests[3]->arguments[2] ?? null);
        self::assertSame('test', $runner->requests[4]->arguments[2] ?? null);
    }

    public function test_test_summary_parses_phpunit_failure_form_without_overriding_process_exit(): void
    {
        $runner = new VerifyStepFakeProcessRunner([
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, '{"environment":"testing"}'),
            new ProcessResult([], 0, 'cached'),
            new ProcessResult([], 0, 'cleared'),
            new ProcessResult([], 1, 'Tests: 3, Assertions: 7, Failures: 1'),
        ]);

        $result = $this->step($runner)->execute($this->context([], false, 11));

        self::assertTrue($result->isFailed());
        self::assertSame(1, $result->exitCode);
        $summary = $this->summaryFromResult($result);
        self::assertSame('failed', $summary['status'] ?? null);
        self::assertSame(3, $summary['tests'] ?? null);
        self::assertSame(7, $summary['assertions'] ?? null);
        self::assertSame(1, $summary['failures'] ?? null);
    }

    public function test_test_summary_parses_pest_passed_form(): void
    {
        $runner = new VerifyStepFakeProcessRunner([
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, '{"environment":"testing"}'),
            new ProcessResult([], 0, 'cached'),
            new ProcessResult([], 0, 'cleared'),
            new ProcessResult([], 0, 'Tests  3 passed (7 assertions)'),
        ]);

        $result = $this->step($runner)->execute($this->context([], false, 11));

        self::assertTrue($result->isSuccessful(), $result->message);
        $summary = $this->summaryFromResult($result);
        self::assertSame('passed', $summary['status'] ?? null);
        self::assertSame(3, $summary['tests'] ?? null);
        self::assertSame(7, $summary['assertions'] ?? null);
    }

    public function test_optional_project_phpstan_runs_only_when_enabled_and_configured(): void
    {
        file_put_contents($this->projectDirectory.'/phpstan.neon', "parameters:\n    level: 0\n");
        mkdir($this->projectDirectory.'/vendor/bin', 0777, true);
        file_put_contents($this->projectDirectory.'/vendor/bin/phpstan', 'phpstan');
        $runner = new VerifyStepFakeProcessRunner([
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, '{"environment":"testing"}'),
            new ProcessResult([], 0, 'cached'),
            new ProcessResult([], 0, 'cleared'),
            new ProcessResult([], 0, 'Tests: 1 passed'),
            new ProcessResult([], 0, 'No errors'),
        ]);

        $result = $this->step($runner)->execute($this->context(['verifyPhpstan' => true], false, 11));

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertCount(6, $runner->requests);
        self::assertSame('analyse', $runner->requests[5]->arguments[1] ?? null);
        self::assertSame('-c', $runner->requests[5]->arguments[2] ?? null);
        self::assertSame(realpath($this->projectDirectory.'/phpstan.neon'), $runner->requests[5]->arguments[3] ?? null);
    }

    public function test_changed_file_traversal_is_rejected_without_running_a_command(): void
    {
        $runner = new VerifyStepFakeProcessRunner;
        $result = $this->step($runner)->execute($this->context([
            'changedFiles' => ['../outside.php', 'app/../outside.php'],
        ], false, 11));

        self::assertTrue($result->isFailed());
        self::assertSame([], $result->data['checks'] ?? null);
        self::assertSame([], $runner->requests);
        self::assertFileExists($this->projectDirectory.'/.laravel-upgrade/findings.jsonl');
    }

    public function test_library_without_artisan_skips_application_checks(): void
    {
        unlink($this->projectDirectory.'/artisan');
        $runner = new VerifyStepFakeProcessRunner([new ProcessResult([], 0, 'valid')]);

        $result = $this->step($runner)->execute($this->context(['projectType' => 'library'], false, 11));

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertCount(1, $runner->requests);
        $checks = $result->data['checks'] ?? null;
        self::assertIsArray($checks);
        $artisanChecks = array_values(array_filter(
            $checks,
            static fn (mixed $check): bool => is_array($check) && in_array($check['id'] ?? null, ['about', 'routes', 'config-cache', 'config-clear', 'tests'], true),
        ));
        self::assertNotEmpty($artisanChecks);
        self::assertSame('library-project', $artisanChecks[0]['reason'] ?? null);
    }

    public function test_findings_are_persisted_idempotently_across_repeated_verification(): void
    {
        $first = new VerifyStepFakeProcessRunner([
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, '{"environment":"testing"}'),
            new ProcessResult([], 4, '', 'bad config'),
            new ProcessResult([], 0, 'cleared'),
            new ProcessResult([], 0, 'Tests: 1 passed'),
        ]);
        $second = new VerifyStepFakeProcessRunner([
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, '{"environment":"testing"}'),
            new ProcessResult([], 4, '', 'bad config'),
            new ProcessResult([], 0, 'cleared'),
            new ProcessResult([], 0, 'Tests: 1 passed'),
        ]);

        self::assertTrue($this->step($first)->execute($this->context([], false, 11))->isFailed());
        self::assertTrue($this->step($second)->execute($this->context([], false, 11))->isFailed());
        $lines = file($this->projectDirectory.'/.laravel-upgrade/findings.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        self::assertIsArray($lines);
        self::assertCount(1, $lines);
    }

    /** @return array<string, string> */
    private function snapshot(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->projectDirectory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[$file->getPathname()] = file_get_contents($file->getPathname()) ?: '';
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @param  array<int|string, mixed>  $checks
     * @return array<int|string, mixed>
     */
    private function commandAt(array $checks, int $index): array
    {
        $check = $checks[$index] ?? null;

        if (! is_array($check) || ! is_array($check['command'] ?? null)) {
            return [];
        }

        return $check['command'];
    }

    /**
     * @param  array<int|string, mixed>  $checks
     * @return array<string, mixed>
     */
    private function checkById(array $checks, string $id): array
    {
        foreach ($checks as $check) {
            if (is_array($check) && ($check['id'] ?? null) === $id) {
                $normalized = [];

                foreach ($check as $key => $value) {
                    if (is_string($key)) {
                        $normalized[$key] = $value;
                    }
                }

                return $normalized;
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function summaryFromResult(StepResult $result): array
    {
        $checks = $result->data['checks'] ?? null;

        if (! is_array($checks)) {
            return [];
        }

        $check = $this->checkById($checks, 'tests-summary');
        $summary = $check['summary'] ?? null;

        if (! is_array($summary)) {
            return [];
        }

        $normalized = [];

        foreach ($summary as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $options */
    private function context(array $options = [], bool $planMode = false, int $target = 11): UpgradeContext
    {
        return new UpgradeContext(
            $this->projectDirectory,
            new UpgradePlan(10, $target, $planMode),
            'verify-test',
            $options,
        );
    }

    private function step(VerifyStepFakeProcessRunner $runner): VerifyStep
    {
        return new VerifyStep($runner);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo instanceof \SplFileInfo) {
                continue;
            }

            $fileInfo->isDir() ? rmdir($fileInfo->getPathname()) : unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}

/** @internal */
final class VerifyStepFakeProcessRunner implements ProcessRunner
{
    /** @var list<ProcessRequest> */
    public array $requests = [];

    /** @var list<ProcessResult> */
    private array $results;

    /** @param list<ProcessResult> $results */
    public function __construct(array $results = [])
    {
        $this->results = $results;
    }

    public function run(ProcessRequest $request): ProcessResult
    {
        $this->requests[] = $request;
        $result = array_shift($this->results);

        if (! $result instanceof ProcessResult) {
            throw new RuntimeException('Unexpected verification process request: '.$request->executable());
        }

        return new ProcessResult($request->arguments, $result->exitCode, $result->output, $result->errorOutput);
    }
}
