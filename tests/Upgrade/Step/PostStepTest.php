<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\PostStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PostStepTest extends TestCase
{
    private string $projectDirectory;

    /** @var list<string> */
    private array $temporaryDataFiles = [];

    protected function setUp(): void
    {
        $this->projectDirectory = sys_get_temp_dir().'/laravel-upgrade-post-'.bin2hex(random_bytes(8));
        mkdir($this->projectDirectory.'/database/migrations', 0777, true);
        file_put_contents($this->projectDirectory.'/composer.json', '{"require":{"laravel/framework":"^10.0"}}');
        file_put_contents($this->projectDirectory.'/artisan', "<?php\n");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDirectory);

        foreach ($this->temporaryDataFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_plan_previews_only_applicable_commands_and_does_not_write(): void
    {
        $this->writeLock(['laravel/sanctum', 'laravel/passport']);
        $runner = new PostStepFakeProcessRunner;
        $result = $this->step($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame([], $runner->requests);
        self::assertDirectoryDoesNotExist($this->projectDirectory.'/.laravel-upgrade');
        $commands = $result->data['commands'] ?? null;
        self::assertIsArray($commands);
        self::assertCount(9, $commands);

        $statuses = array_map(
            static fn (mixed $command): mixed => is_array($command) ? ($command['status'] ?? null) : null,
            $commands,
        );
        self::assertSame(
            ['preview', 'preview', 'skipped', 'skipped', 'skipped', 'preview', 'preview', 'preview', 'preview'],
            $statuses,
        );
        $preview = $commands[0] ?? null;
        self::assertIsArray($preview);
        $command = $preview['command'] ?? null;
        self::assertIsArray($command);
        self::assertSame(PHP_BINARY, $command[0] ?? null);
        self::assertSame('vendor:publish', $command[2] ?? null);
    }

    public function test_apply_runs_all_commands_after_a_failure_and_persists_a_medium_finding(): void
    {
        $this->writeLock(['laravel/sanctum', 'laravel/passport']);
        file_put_contents(
            $this->projectDirectory.'/database/migrations/2020_01_01_000000_create_personal_access_tokens_table.php',
            "<?php\n",
        );
        $runner = new PostStepFakeProcessRunner([
            new ProcessResult([], 7, '', 'passport publish failed'),
            new ProcessResult([], 0, 'autoloaded'),
            new ProcessResult([], 0, 'config cleared'),
            new ProcessResult([], 0, 'routes cleared'),
            new ProcessResult([], 0, 'views cleared'),
        ]);
        $result = $this->step($runner)->execute($this->context());

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame(1, $result->findingsCount);
        self::assertCount(5, $runner->requests);
        self::assertSame('vendor:publish', $runner->requests[0]->arguments[2] ?? null);
        self::assertSame('--tag=passport-migrations', $runner->requests[0]->arguments[3] ?? null);
        self::assertSame('dump-autoload', $runner->requests[1]->arguments[1] ?? null);
        self::assertSame('config:clear', $runner->requests[2]->arguments[2] ?? null);
        self::assertSame('route:clear', $runner->requests[3]->arguments[2] ?? null);
        self::assertSame('view:clear', $runner->requests[4]->arguments[2] ?? null);

        $commands = $result->data['commands'] ?? null;
        self::assertIsArray($commands);
        $failed = $commands[1] ?? null;
        self::assertIsArray($failed);
        self::assertSame('failed', $failed['status'] ?? null);
        self::assertSame(7, $failed['exitCode'] ?? null);

        $findingsPath = $this->projectDirectory.'/.laravel-upgrade/findings.jsonl';
        self::assertFileExists($findingsPath);
        $lines = file($findingsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);
        self::assertStringContainsString('"severity":"medium"', $lines[0]);
        self::assertStringContainsString('laravelUpgrade.post.passport-migrations', $lines[0]);
    }

    public function test_existing_migrations_skip_publish_unless_explicitly_removed_ignore_migrations(): void
    {
        $this->writeLock(['laravel/sanctum']);
        file_put_contents(
            $this->projectDirectory.'/database/migrations/2020_01_01_000000_create_personal_access_tokens_table.php',
            "<?php\n",
        );
        $runner = new PostStepFakeProcessRunner([
            new ProcessResult([], 0, 'published despite marker'),
            new ProcessResult([], 0, 'autoloaded'),
            new ProcessResult([], 0, 'config cleared'),
            new ProcessResult([], 0, 'routes cleared'),
            new ProcessResult([], 0, 'views cleared'),
        ]);
        $result = $this->step($runner)->execute($this->context(['ignoreMigrationsRemoved' => ['laravel/sanctum']]));

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertCount(5, $runner->requests);
        self::assertSame('--tag=sanctum-migrations', $runner->requests[0]->arguments[3] ?? null);
        self::assertSame(0, $result->findingsCount);
    }

    public function test_missing_artisan_skips_artisan_commands_but_still_runs_composer(): void
    {
        unlink($this->projectDirectory.'/artisan');
        $runner = new PostStepFakeProcessRunner([new ProcessResult([], 0, 'autoloaded')]);
        $result = $this->step($runner)->execute($this->context());

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertCount(1, $runner->requests);
        self::assertSame('dump-autoload', $runner->requests[0]->arguments[1] ?? null);
        $commands = $result->data['commands'] ?? null;
        self::assertIsArray($commands);
        $statuses = array_map(
            static fn (mixed $command): mixed => is_array($command) ? ($command['status'] ?? null) : null,
            $commands,
        );
        self::assertSame(['skipped', 'skipped', 'skipped', 'skipped', 'skipped', 'success', 'skipped', 'skipped', 'skipped'], $statuses);
    }

    public function test_malformed_post_install_data_fails_without_launching_commands(): void
    {
        $path = $this->projectDirectory.'/bad-post-install.json';
        file_put_contents($path, '{"11":[{"type":"command"}]}');
        $this->temporaryDataFiles[] = $path;
        $runner = new PostStepFakeProcessRunner;
        $result = (new PostStep($runner, $path))->execute($this->context());

        self::assertTrue($result->isFailed());
        self::assertSame(1, $result->exitCode);
        self::assertSame('post-install-data', $result->data['check'] ?? null);
        self::assertSame([], $runner->requests);
    }

    public function test_process_launch_failure_fails_the_step_and_persists_prior_findings(): void
    {
        $this->writeLock(['laravel/sanctum']);
        $runner = new PostStepFakeProcessRunner([
            new ProcessResult([], 9, '', 'publish failed'),
        ]);
        $result = $this->step($runner)->execute($this->context());

        self::assertTrue($result->isFailed());
        self::assertSame(1, $result->exitCode);
        self::assertSame('process', $result->data['check'] ?? null);
        self::assertStringContainsString('Could not launch post-install command "composer-dump-autoload"', $result->message);
        self::assertFileExists($this->projectDirectory.'/.laravel-upgrade/findings.jsonl');
        self::assertCount(2, $runner->requests);
    }

    /** @param list<string> $packages */
    private function writeLock(array $packages): void
    {
        file_put_contents($this->projectDirectory.'/composer.lock', json_encode([
            'packages' => array_map(static fn (string $name): array => ['name' => $name, 'version' => '1.0.0'], $packages),
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $options */
    private function context(array $options = [], bool $planMode = false): UpgradeContext
    {
        return new UpgradeContext(
            $this->projectDirectory,
            new UpgradePlan(10, 11, $planMode),
            'post-test',
            $options,
        );
    }

    private function step(PostStepFakeProcessRunner $runner): PostStep
    {
        return new PostStep($runner);
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
            /** @var \SplFileInfo $fileInfo */
            $fileInfo->isDir() ? rmdir($fileInfo->getPathname()) : unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}

/** @internal */
final class PostStepFakeProcessRunner implements ProcessRunner
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

        if ($this->results === []) {
            throw new RuntimeException('Unexpected post-install process: '.$request->executable());
        }

        $result = array_shift($this->results);

        if (! $result instanceof ProcessResult) {
            throw new RuntimeException('Post-install process result queue was corrupted.');
        }

        return new ProcessResult($request->arguments, $result->exitCode, $result->output, $result->errorOutput);
    }
}
