<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * One-command upgrade: chains deps → composer update → rector → verification.
 * Each step is verified before the next begins.
 */
final class ToCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('to')
            ->setDescription('Run the full Laravel upgrade flow up to the target major')
            ->addArgument('target-major', InputArgument::REQUIRED, 'Target Laravel major version (e.g. 11)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print plan without touching anything')
            ->addOption('skip-tests', null, InputOption::VALUE_NONE, 'Skip artisan test at the end')
            ->addOption('working-dir', 'd', InputOption::VALUE_REQUIRED, 'Project directory', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $dirOption = $input->getOption('working-dir');
        $workingDirectory = is_string($dirOption) && $dirOption !== '' ? $dirOption : '.';
        $dryRun = (bool) $input->getOption('dry-run');
        $skipTests = (bool) $input->getOption('skip-tests');

        $targetRaw = $input->getArgument('target-major');
        $targetMajor = is_scalar($targetRaw) ? (int) $targetRaw : 0;

        if (! in_array($targetMajor, [11, 12, 13], true)) {
            $style->error('Supported target majors: 11, 12, 13.');

            return Command::FAILURE;
        }

        // Detect current major from composer.json.
        $manifestPath = rtrim($workingDirectory, '/').'/composer.json';

        if (! is_file($manifestPath)) {
            $style->error(sprintf('No composer.json found in "%s".', $workingDirectory));

            return Command::FAILURE;
        }

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            $style->error('composer.json contains invalid JSON.');

            return Command::FAILURE;
        }

        $frameworkConstraint = (is_array($manifest['require'] ?? null) ? ($manifest['require']['laravel/framework'] ?? null) : null);
        $currentMajor = null;

        if (is_string($frameworkConstraint) && preg_match('/[\^~>]+\s*(\d+)\./', $frameworkConstraint, $m) === 1) {
            $currentMajor = (int) $m[1];
        }

        // Show plan summary.
        $style->title(sprintf('Laravel %s → Laravel %d', $currentMajor !== null ? (string) $currentMajor : '?', $targetMajor));

        $style->table(
            ['Setting', 'Value'],
            [
                ['Working directory', $workingDirectory],
                ['Current major', $currentMajor !== null ? (string) $currentMajor : 'unknown'],
                ['Target major', (string) $targetMajor],
                ['PHP', PHP_VERSION],
                ['Dry run', $dryRun ? 'yes' : 'no'],
                ['Skip tests', $skipTests ? 'yes' : 'no'],
            ]
        );

        // Preflight checks (plan P4-03).
        $preflightFailures = [];

        $requiredPhp = match ($targetMajor) {
            13 => 80300,
            12 => 80200,
            default => 80200,
        };

        if ($requiredPhp > PHP_VERSION_ID) {
            $preflightFailures[] = sprintf(
                'PHP %s is too old for Laravel %d (requires >= %s).',
                PHP_VERSION,
                $targetMajor,
                implode('.', [intdiv($requiredPhp, 10000), intdiv($requiredPhp % 10000, 100), $requiredPhp % 100])
            );
        }

        // SQLite version check when sqlite is used.
        $envFile = $workingDirectory.'/.env';

        if (is_file($envFile) && str_contains((string) file_get_contents($envFile), 'DB_CONNECTION=sqlite')) {
            $sqliteProcess = new Process(['php', '-r', 'echo (new PDO("sqlite::memory:"))->query("select sqlite_version()")->fetchColumn();']);
            $sqliteProcess->run();
            $sqliteVersion = trim($sqliteProcess->getOutput());

            if ($sqliteVersion !== '' && version_compare($sqliteVersion, '3.26.0', '<')) {
                $preflightFailures[] = sprintf('SQLite %s is too old for Laravel 11+ (requires >= 3.26).', $sqliteVersion);
            }
        }

        if ($preflightFailures !== []) {
            $style->error('Preflight failures:\n'.implode("\n", array_map(fn ($f) => '  - '.$f, $preflightFailures)));

            return 2; // preflight failure per plan exit codes
        }

        if ($currentMajor !== null && $currentMajor >= $targetMajor) {
            $style->success(sprintf('Already on Laravel %d — nothing to do.', $currentMajor));

            return Command::SUCCESS;
        }

        $stateDirectory = $workingDirectory.'/.laravel-upgrade';

        $writeState = function (string $completedStep) use ($stateDirectory, $targetMajor): void {
            if (! is_dir($stateDirectory)) {
                @mkdir($stateDirectory, 0777, true);
            }

            file_put_contents($stateDirectory.'/state.json', json_encode([
                'target' => $targetMajor,
                'completed_step' => $completedStep,
                'timestamp' => date('c'),
            ], JSON_PRETTY_PRINT)."\n");
        };

        // Step 1: deps.
        $style->section('Step 1/3 — Dependencies');

        if (! $dryRun) {
            $writeState('');
        }

        $depsInput = new ArrayInput([
            'command' => 'deps',
            'target-major' => (string) $targetMajor,
            '--working-dir' => $workingDirectory,
        ] + ($dryRun ? ['--dry-run' => true] : []));

        $application = $this->getApplication();
        $depsExit = 0;

        if ($application instanceof Application) {
            $depsExit = $application->find('deps')->run($depsInput, $output);
        }

        if ($depsExit !== 0 && ! $dryRun) {
            $style->error('Dependency planning failed. Fix and re-run.');

            return 3;
        }

        if (! $dryRun) {
            $writeState('deps');
        }

        if ($dryRun) {
            $style->note('Dry run complete.');

            return Command::SUCCESS;
        }

        // Step 2: composer update -W.
        $style->section('Step 2/3 — Install');

        $updateProcess = new Process(
            ['composer', 'update', '--with-all-dependencies', '--no-interaction', '--no-progress'],
            $workingDirectory
        );
        $updateProcess->setTimeout(1800);
        $updateProcess->run(function (string $type, string $buffer): void {
            echo $buffer;
        });

        if ($updateProcess->getExitCode() !== 0) {
            $style->error('composer update failed.');

            return 3;
        }

        $writeState('install');

        // Step 3: Rector code transformation.
        $style->section('Step 3/3 — Code transformation');

        $configPath = dirname(__DIR__, 4).'/config/laravel-'.$targetMajor.'.php';
        $rectorProcess = new Process(
            ['vendor/bin/rector', 'process', '--config', $configPath, '--no-progress-bar'],
            $workingDirectory
        );
        $rectorProcess->setTimeout(1800);
        $rectorProcess->run(function (string $type, string $buffer): void {
            echo $buffer;
        });

        if ($rectorProcess->getExitCode() !== 0) {
            $style->warning('Rector reported issues — review output above.');
        }

        // Post-step: artisan commands.
        $style->section('Post-step');

        $postCommands = [
            'composer dump-autoload' => ['composer', 'dump-autoload'],
            'php artisan config:clear' => ['php', 'artisan', 'config:clear'],
            'php artisan route:clear' => ['php', 'artisan', 'route:clear'],
            'php artisan view:clear' => ['php', 'artisan', 'view:clear'],
        ];

        foreach ($postCommands as $label => $cmd) {
            $postProcess = new Process($cmd, $workingDirectory);
            $postProcess->setTimeout(120);
            $postProcess->run();

            if ($postProcess->isSuccessful()) {
                $style->text('✔ '.$label);
            } else {
                $style->text('⚠ '.$label.' (non-fatal)');
            }
        }

        $writeState('rector');

        // Verification.
        $style->section('Verification');

        $verifyCommands = [
            'Validate composer.json' => 'composer validate --strict --no-check-lock',
            'Clear config cache' => 'php artisan config:clear',
            'Boot application' => 'php artisan about',
        ];

        $verifyFailures = [];

        foreach ($verifyCommands as $label => $command) {
            $verifyProcess = new Process(['sh', '-c', $command], $workingDirectory);
            $verifyProcess->setTimeout(300);
            $verifyProcess->run();

            if ($verifyProcess->isSuccessful()) {
                $style->text('✔ '.$label);
            } else {
                $verifyFailures[] = $label;
                $style->text('✘ '.$label);
            }
        }

        if (! $skipTests) {
            $testProcess = new Process(['php', 'artisan', 'test', '--compact'], $workingDirectory);
            $testProcess->setTimeout(900);
            $testProcess->run();

            if ($testProcess->isSuccessful()) {
                $style->text('✔ Tests');
            } else {
                $verifyFailures[] = 'Tests failed';
                $style->text('✘ Tests');
            }
        }

        if ($verifyFailures !== []) {
            $style->error('Verification failures: '.implode(', ', $verifyFailures));

            return 1;
        }

        $writeState('done');
        $style->success(sprintf('Upgrade to Laravel %d complete!', $targetMajor));

        return Command::SUCCESS;
    }
}
