<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\StepExecutionResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\UpgradeReportGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonRepository;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\SkeletonSyncStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;

final class SkeletonStepTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/skel-'.uniqid();
        mkdir($this->tmpDir.'/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $fileInfo->isDir() ? @rmdir($fileInfo->getPathname()) : @unlink($fileInfo->getPathname());
        }
        @rmdir($this->tmpDir);
    }

    public function test_sync_merges_missing_keys(): void
    {
        file_put_contents($this->tmpDir.'/config/cache.php',
            "<?php\n\nreturn [\n    'default' => 'file',\n];\n");

        $step = new SkeletonStep;
        // The 13 skeleton has serializable_classes in cache.php which is
        // missing from the project config, so it gets merged.
        $merged = $step->sync($this->tmpDir.'/config', 13);

        self::assertSame(['cache.php'], $merged);
    }

    public function test_upstream_config_path_returns_null_for_missing(): void
    {
        $step = new SkeletonStep;

        self::assertNull($step->upstreamConfigPath(99, 'nonexistent'));
    }

    public function test_partial_snapshots_only_reconcile_config_and_never_infer_file_changes(): void
    {
        $root = $this->tmpDir.'/snapshots';
        mkdir($root.'/10', 0777, true);
        mkdir($root.'/11', 0777, true);
        file_put_contents($root.'/MANIFEST.json', json_encode([
            '10' => ['complete' => false],
            '11' => ['complete' => false],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($root.'/10/old.txt', 'old');
        file_put_contents($root.'/11/new.txt', 'new');
        file_put_contents($this->tmpDir.'/old.txt', 'project old');
        file_put_contents($this->tmpDir.'/.env.example', "OLD=value\n");
        file_put_contents($this->tmpDir.'/.env', "OLD=secret\n");
        $collector = new FindingCollector;

        $result = (new SkeletonStep(new SkeletonRepository($root)))->syncProject(
            $this->tmpDir,
            10,
            11,
            $collector
        );

        self::assertSame([], $result['added']);
        self::assertSame([], $result['removed']);
        self::assertSame([], $result['modified']);
        self::assertSame([], $result['renamed']);
        self::assertFileExists($this->tmpDir.'/old.txt');
        self::assertFileDoesNotExist($this->tmpDir.'/new.txt');
        self::assertSame(1, $collector->count());
        self::assertSame('laravelUpgrade.skeletonSyncSkipped', $collector->all()[0]->ruleId);
        self::assertSame("OLD=secret\n", file_get_contents($this->tmpDir.'/.env'));
    }

    public function test_complete_snapshots_merge_env_and_non_php_files_without_writing_env(): void
    {
        $root = $this->tmpDir.'/complete-snapshots';
        mkdir($root.'/12/config', 0777, true);
        mkdir($root.'/13/config', 0777, true);
        mkdir($root.'/12/routes', 0777, true);
        mkdir($root.'/13/routes', 0777, true);
        file_put_contents($root.'/MANIFEST.json', json_encode([
            '12' => ['complete' => true],
            '13' => ['complete' => true],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($root.'/12/config/session.php', "<?php\nreturn [];\n");
        file_put_contents($root.'/13/config/session.php', "<?php\nreturn ['serialization' => 'json'];\n");
        file_put_contents($root.'/12/.env.example', "APP_NAME=Laravel\n");
        file_put_contents($root.'/13/.env.example', "APP_NAME=Laravel\nNEW_KEY=target\n");
        file_put_contents($root.'/12/.gitignore', "base\n");
        file_put_contents($root.'/13/.gitignore', "target\n");
        file_put_contents($root.'/12/package.json', "{\"dependencies\":{\"vite\":\"old\"}}\n");
        file_put_contents($root.'/13/package.json', "{\"dependencies\":{\"vite\":\"new\"}}\n");
        file_put_contents($root.'/12/routes/web.php', "<?php\n// old route\n");
        file_put_contents($root.'/13/routes/web.php', "<?php\n// target route\n");
        file_put_contents($this->tmpDir.'/config/session.php', "<?php\nreturn [];\n");
        file_put_contents($this->tmpDir.'/.env.example', "APP_NAME=Laravel\n");
        file_put_contents($this->tmpDir.'/.env', "APP_KEY=secret\n");
        file_put_contents($this->tmpDir.'/.gitignore', "base\n");
        file_put_contents($this->tmpDir.'/package.json', "{\"dependencies\":{\"vite\":\"project\"}}\n");
        mkdir($this->tmpDir.'/routes', 0777, true);
        file_put_contents($this->tmpDir.'/routes/web.php', "<?php\n// project route\n");
        $collector = new FindingCollector;

        $result = (new SkeletonStep(new SkeletonRepository($root)))->syncProject(
            $this->tmpDir,
            12,
            13,
            $collector
        );

        self::assertContains('config/session.php', $result['changed']);
        self::assertContains('.env.example', $result['changed']);
        self::assertContains('.gitignore', $result['changed']);
        $session = file_get_contents($this->tmpDir.'/config/session.php');
        $envExample = file_get_contents($this->tmpDir.'/.env.example');
        self::assertIsString($session);
        self::assertIsString($envExample);
        self::assertStringContainsString("'serialization' => 'php'", $session);
        self::assertStringContainsString('NEW_KEY=target', $envExample);
        self::assertSame("APP_KEY=secret\n", file_get_contents($this->tmpDir.'/.env'));
        self::assertSame("target\n", file_get_contents($this->tmpDir.'/.gitignore'));
        self::assertSame("{\"dependencies\":{\"vite\":\"project\"}}\n", file_get_contents($this->tmpDir.'/package.json'));
        self::assertSame("<?php\n// project route\n", file_get_contents($this->tmpDir.'/routes/web.php'));
        self::assertNotContains('routes/web.php', $result['changed']);
        $ruleIds = array_map(static fn ($finding): string => $finding->ruleId, $collector->all());
        self::assertContains('laravelUpgrade.packageJsonDependencies', $ruleIds);
        self::assertContains('laravelUpgrade.skeletonRouteChanged', $ruleIds);
        self::assertContains('laravelUpgrade.envExampleMissingFromEnvironment', $ruleIds);
        $environmentFinding = array_values(array_filter(
            $collector->all(),
            static fn ($finding): bool => $finding->ruleId === 'laravelUpgrade.envExampleMissingFromEnvironment',
        ));
        self::assertCount(1, $environmentFinding);
        self::assertStringContainsString('NEW_KEY', $environmentFinding[0]->message);
        $packageFinding = array_values(array_filter(
            $collector->all(),
            static fn ($finding): bool => $finding->ruleId === 'laravelUpgrade.packageJsonDependencies',
        ));
        self::assertCount(1, $packageFinding);
        self::assertStringContainsString('vite', $packageFinding[0]->message);
        self::assertSame([], $result['conflicts']);
        self::assertNotContains('package.json', $result['added']);
        self::assertNotContains('package.json', $result['modified']);
        self::assertNotContains('package.json', $result['removed']);
        self::assertNotContains('package.json', array_keys($result['renamed']));
        self::assertSame(1, count(array_filter(
            $result['changed'],
            static fn (string $relative): bool => $relative === '.gitignore',
        )));
    }

    public function test_missing_project_package_is_advisory_only(): void
    {
        $root = $this->tmpDir.'/package-snapshots';
        mkdir($root.'/10', 0777, true);
        mkdir($root.'/11', 0777, true);
        file_put_contents($root.'/MANIFEST.json', json_encode([
            '10' => ['complete' => true],
            '11' => ['complete' => true],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($root.'/11/package.json', "{\"private\":true}\n");

        $collector = new FindingCollector;
        $result = (new SkeletonStep(new SkeletonRepository($root)))->syncProject(
            $this->tmpDir,
            10,
            11,
            $collector,
        );

        self::assertFileDoesNotExist($this->tmpDir.'/package.json');
        self::assertNotContains('package.json', $result['added']);
        self::assertNotContains('package.json', $result['changed']);
        self::assertSame(['laravelUpgrade.packageJsonDependencies'], array_map(
            static fn ($finding): string => $finding->ruleId,
            $collector->all(),
        ));
    }

    public function test_added_migrations_are_advisory_structure_only_files_are_skipped_and_removed_files_are_not_deleted(): void
    {
        $root = $this->tmpDir.'/policy-snapshots';
        mkdir($root.'/10', 0777, true);
        mkdir($root.'/11/bootstrap', 0777, true);
        mkdir($root.'/11/database/migrations', 0777, true);
        file_put_contents($root.'/MANIFEST.json', json_encode([
            '10' => ['complete' => true],
            '11' => ['complete' => true],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($root.'/10/removed.php', 'removed');
        file_put_contents($root.'/11/new.php', 'new');
        file_put_contents($root.'/11/bootstrap/providers.php', "<?php\nreturn [];\n");
        file_put_contents($root.'/11/database/migrations/2026_01_01_000000_create_cache_table.php', "<?php\n");
        file_put_contents($this->tmpDir.'/removed.php', 'project custom');

        $collector = new FindingCollector;
        $result = (new SkeletonStep(new SkeletonRepository($root)))->syncProject(
            $this->tmpDir,
            10,
            11,
            $collector,
        );

        self::assertFileExists($this->tmpDir.'/new.php');
        self::assertFileDoesNotExist($this->tmpDir.'/bootstrap/providers.php');
        self::assertFileDoesNotExist($this->tmpDir.'/database/migrations/2026_01_01_000000_create_cache_table.php');
        self::assertSame('project custom', file_get_contents($this->tmpDir.'/removed.php'));
        self::assertContains('removed.php', $result['removed']);
        self::assertContains('new.php', $result['added']);
        self::assertSame(['laravelUpgrade.skeletonMigrationAdded', 'laravelUpgrade.skeletonFileRemoved'], array_map(
            static fn ($finding): string => $finding->ruleId,
            $collector->all(),
        ));
    }

    public function test_new_files_preserve_source_mode_and_conflict_artifacts_use_safe_mode(): void
    {
        $root = $this->tmpDir.'/mode-snapshots';
        mkdir($root.'/10', 0777, true);
        mkdir($root.'/11', 0777, true);
        file_put_contents($root.'/MANIFEST.json', json_encode([
            '10' => ['complete' => true],
            '11' => ['complete' => true],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($root.'/10/custom.txt', "base\n");
        file_put_contents($root.'/11/custom.txt', "target\n");
        file_put_contents($root.'/11/target-only.sh', "#!/bin/sh\nexit 0\n");
        chmod($root.'/11/target-only.sh', 0755);
        file_put_contents($this->tmpDir.'/custom.txt', "project\n");

        $result = (new SkeletonStep(new SkeletonRepository($root)))->syncProject($this->tmpDir, 10, 11);

        self::assertContains('custom.txt', $result['conflicts']);
        self::assertSame(0755, fileperms($this->tmpDir.'/target-only.sh') & 0777);
        $artifact = $this->tmpDir.'/.laravel-upgrade/merge/custom.txt.merged';
        self::assertFileExists($artifact);
        self::assertSame(0644, fileperms($artifact) & 0777);
    }

    public function test_public_artisan_and_test_case_use_three_way_sync_but_application_and_feature_tests_are_excluded(): void
    {
        $root = $this->tmpDir.'/three-way-snapshots';
        mkdir($root.'/10/public', 0777, true);
        mkdir($root.'/11/public', 0777, true);
        mkdir($root.'/10/tests', 0777, true);
        mkdir($root.'/11/tests', 0777, true);
        mkdir($root.'/10/app', 0777, true);
        mkdir($root.'/11/app', 0777, true);
        file_put_contents($root.'/MANIFEST.json', json_encode([
            '10' => ['complete' => true],
            '11' => ['complete' => true],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($root.'/10/artisan', "<?php\n// old\n");
        file_put_contents($root.'/11/artisan', "<?php\n// target\n");
        file_put_contents($root.'/10/public/index.php', "<?php\n// old\n");
        file_put_contents($root.'/11/public/index.php', "<?php\n// target\n");
        file_put_contents($root.'/10/tests/TestCase.php', "<?php\n// old\n");
        file_put_contents($root.'/11/tests/TestCase.php', "<?php\n// target\n");
        file_put_contents($root.'/10/tests/Feature.php', "<?php\n// old\n");
        file_put_contents($root.'/11/tests/Feature.php', "<?php\n// target\n");
        file_put_contents($root.'/10/app/Example.php', "<?php\n// old\n");
        file_put_contents($root.'/11/app/Example.php', "<?php\n// target\n");
        file_put_contents($this->tmpDir.'/artisan', "<?php\n// old\n");
        mkdir($this->tmpDir.'/public', 0777, true);
        file_put_contents($this->tmpDir.'/public/index.php', "<?php\n// old\n");
        mkdir($this->tmpDir.'/tests', 0777, true);
        file_put_contents($this->tmpDir.'/tests/TestCase.php', "<?php\n// old\n");
        file_put_contents($this->tmpDir.'/tests/Feature.php', "<?php\n// old\n");
        mkdir($this->tmpDir.'/app', 0777, true);
        file_put_contents($this->tmpDir.'/app/Example.php', "<?php\n// old\n");

        $result = (new SkeletonStep(new SkeletonRepository($root)))->syncProject($this->tmpDir, 10, 11);

        self::assertSame("<?php\n// target\n", file_get_contents($this->tmpDir.'/artisan'));
        self::assertSame("<?php\n// target\n", file_get_contents($this->tmpDir.'/public/index.php'));
        self::assertSame("<?php\n// target\n", file_get_contents($this->tmpDir.'/tests/TestCase.php'));
        self::assertSame("<?php\n// old\n", file_get_contents($this->tmpDir.'/tests/Feature.php'));
        self::assertSame("<?php\n// old\n", file_get_contents($this->tmpDir.'/app/Example.php'));
        self::assertContains('artisan', $result['changed']);
        self::assertContains('public/index.php', $result['changed']);
        self::assertContains('tests/TestCase.php', $result['changed']);
    }

    public function test_config_app_merge_preserves_custom_providers_and_inserts_new_keys(): void
    {
        $root = $this->tmpDir.'/config-snapshots';
        mkdir($root.'/10/config', 0777, true);
        mkdir($root.'/11/config', 0777, true);
        file_put_contents($root.'/MANIFEST.json', json_encode([
            '10' => ['complete' => true],
            '11' => ['complete' => true],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($root.'/10/config/app.php', "<?php\nreturn [\n    'name' => 'Laravel',\n    'providers' => [\n        'FrameworkProvider',\n    ],\n];\n");
        file_put_contents($root.'/11/config/app.php', "<?php\nreturn [\n    'name' => 'Laravel 11',\n    'new_key' => true,\n    'providers' => [\n        'FrameworkProvider',\n        'NewProvider',\n    ],\n];\n");
        file_put_contents($this->tmpDir.'/config/app.php', "<?php\n// Keep this comment.\nreturn [\n    'name' => 'My application',\n    'providers' => [\n        'FrameworkProvider',\n        'AppProvider',\n    ],\n];\n");

        $collector = new FindingCollector;
        $result = (new SkeletonStep(new SkeletonRepository($root)))->syncProject(
            $this->tmpDir,
            10,
            11,
            $collector,
        );
        $contents = file_get_contents($this->tmpDir.'/config/app.php');

        self::assertIsString($contents);
        self::assertStringContainsString('// Keep this comment.', $contents);
        self::assertStringContainsString("'new_key' => true", $contents);
        self::assertStringContainsString("'name' => 'My application'", $contents);
        self::assertStringContainsString("'AppProvider'", $contents);
        self::assertStringNotContainsString("'NewProvider'", $contents);
        self::assertContains('config/app.php', $result['changed']);
    }

    public function test_complete_vendored_snapshot_matrix_keeps_user_files_and_is_idempotent(): void
    {
        $repository = new SkeletonRepository;
        $transitions = [[10, 11], [11, 12], [12, 13]];

        foreach ($transitions as [$from, $target]) {
            $project = $this->tmpDir.'/matrix-'.$from.'-'.$target;
            $this->copyDirectory($repository->path($from), $project);
            $beforePackage = file_get_contents($project.'/package.json');
            $beforeRoute = file_get_contents($project.'/routes/web.php');

            if ($from === 10 && $target === 11) {
                $app = file_get_contents($project.'/config/app.php');
                self::assertIsString($app);
                $app = str_replace(
                    'App\\Providers\\RouteServiceProvider::class,',
                    "App\\Providers\\RouteServiceProvider::class,\n        App\\Providers\\CustomProvider::class,",
                    $app,
                );
                file_put_contents($project.'/config/app.php', $app);
                $envExample = file_get_contents($project.'/.env.example');
                self::assertIsString($envExample);
                $envExample = preg_replace('/^APP_NAME=.*\R/m', '', $envExample);
                self::assertIsString($envExample);
                file_put_contents($project.'/.env.example', $envExample);
            }

            $collector = new FindingCollector;
            $step = new SkeletonStep($repository);
            $result = $step->syncProject($project, $from, $target, $collector);

            self::assertSame([], $result['conflicts']);
            self::assertSame($beforePackage, file_get_contents($project.'/package.json'));
            self::assertSame($beforeRoute, file_get_contents($project.'/routes/web.php'));
            self::assertContains('laravelUpgrade.packageJsonDependencies', array_map(
                static fn ($finding): string => $finding->ruleId,
                $collector->all(),
            ));

            if ($from === 10 && $target === 11) {
                self::assertContains('laravelUpgrade.skeletonRouteChanged', array_map(
                    static fn ($finding): string => $finding->ruleId,
                    $collector->all(),
                ));
                self::assertFileDoesNotExist($project.'/bootstrap/providers.php');
                $app = file_get_contents($project.'/config/app.php');
                self::assertIsString($app);
                self::assertStringContainsString('CustomProvider::class', $app);
                self::assertNotEmpty($result['changed']);
                $envExample = file_get_contents($project.'/.env.example');
                self::assertIsString($envExample);
                self::assertDoesNotMatchRegularExpression('/^APP_NAME=/m', $envExample);
                self::assertStringContainsString('APP_TIMEZONE=UTC', $envExample);
                self::assertStringContainsString('# APP_MAINTENANCE_STORE=database', $envExample);
                self::assertLessThan(
                    strpos($envExample, 'APP_URL=http://localhost'),
                    strpos($envExample, 'APP_TIMEZONE=UTC'),
                );
                self::assertLessThan(
                    strpos($envExample, 'APP_MAINTENANCE_DRIVER=file'),
                    strpos($envExample, 'APP_LOCALE=en'),
                );
                self::assertLessThan(
                    strpos($envExample, 'PHP_CLI_SERVER_WORKERS=4'),
                    strpos($envExample, 'APP_MAINTENANCE_DRIVER=file'),
                );
                self::assertLessThan(
                    strpos($envExample, 'BCRYPT_ROUNDS=12'),
                    strpos($envExample, 'PHP_CLI_SERVER_WORKERS=4'),
                );
            }

            if ($from === 11 && $target === 12) {
                self::assertSame(
                    file_get_contents($repository->path(12).'/artisan'),
                    file_get_contents($project.'/artisan'),
                );
            }

            if ($from === 12 && $target === 13) {
                $session = file_get_contents($project.'/config/session.php');
                self::assertIsString($session);
                self::assertStringContainsString("'serialization' => 'php'", $session);
            }

            // Every complete transition must be idempotent and its dry-run
            // must leave the project byte-for-byte unchanged.
            $afterFirstRun = $this->fileContents($project);
            $step->syncProject($project, $from, $target, new FindingCollector);
            self::assertSame($afterFirstRun, $this->fileContents($project));

            $planProject = $this->tmpDir.'/matrix-plan-'.$from.'-'.$target;
            $this->copyDirectory($repository->path($from), $planProject);
            $beforePlan = $this->fileContents($planProject);
            $step->syncProject($planProject, $from, $target, new FindingCollector, true);
            self::assertSame($beforePlan, $this->fileContents($planProject));

            if ($from === 10 && $target === 11) {
                self::assertContains('laravelUpgrade.configBehaviourChange', array_map(
                    static fn ($finding): string => $finding->ruleId,
                    $collector->all(),
                ));
                $filesystemFindings = array_values(array_filter(
                    $collector->all(),
                    static fn ($finding): bool => $finding->ruleId === 'laravelUpgrade.configBehaviourChange'
                        && str_contains($finding->message, 'filesystem root'),
                ));
                self::assertCount(1, $filesystemFindings);
            }
        }
    }

    public function test_real_laravel_12_to_13_sync_has_a_session_report_item_and_is_plan_neutral(): void
    {
        $repository = new SkeletonRepository;
        $project = $this->tmpDir.'/e2e-12-13';
        $this->copyDirectory($repository->path(12), $project);

        $step = new SkeletonSyncStep(new SkeletonStep($repository));
        $context = new UpgradeContext(
            $project,
            new UpgradePlan(12, 13),
            'e2e-12-13',
        );
        $result = $step->execute($context);

        self::assertTrue($result->isSuccessful(), $result->message);
        $sync = $result->data['sync'] ?? null;
        self::assertIsArray($sync);
        self::assertSame([], $sync['conflicts'] ?? null);
        self::assertStringContainsString(
            "'serialization' => 'php'",
            (string) file_get_contents($project.'/config/session.php'),
        );

        (new UpgradeReportGenerator)->recordStep(
            $context,
            new StepExecutionResult(
                '12->13',
                12,
                13,
                'skeleton',
                $result,
            ),
        );
        $report = (string) file_get_contents($project.'/.laravel-upgrade/report.json');
        self::assertStringContainsString('laravelUpgrade.configBehaviourChange', $report);

        $afterApply = $this->fileContents($project);
        $second = $step->execute($context);
        self::assertTrue($second->isSuccessful(), $second->message);
        self::assertSame([], $second->changedFiles);
        self::assertSame($afterApply, $this->fileContents($project));

        $planProject = $this->tmpDir.'/e2e-plan-12-13';
        $this->copyDirectory($repository->path(12), $planProject);
        $beforePlan = $this->fileContents($planProject);
        $planResult = $step->execute(new UpgradeContext(
            $planProject,
            new UpgradePlan(12, 13, true),
            'e2e-plan-12-13',
        ));
        self::assertTrue($planResult->isSuccessful(), $planResult->message);
        self::assertSame($beforePlan, $this->fileContents($planProject));
    }

    public function test_modern_slim_config_deletions_are_not_recreated_by_generic_skeleton_sync(): void
    {
        $repository = new SkeletonRepository;
        $project = $this->tmpDir.'/modern-slim';
        $this->copyDirectory($repository->path(10), $project);

        $result = (new SkeletonStep($repository))->syncProject(
            $project,
            10,
            11,
            new FindingCollector,
            false,
            'modern',
            true,
        );

        self::assertSame([], $result['conflicts']);
        self::assertContains('config/auth.php', $result['deleted']);
        self::assertFileDoesNotExist($project.'/config/auth.php');
        self::assertSame(1, count(array_filter(
            $result['changed'],
            static fn (string $relative): bool => $relative === 'config/auth.php',
        )));
    }

    public function test_modern_conflict_short_circuits_generic_skeleton_sync(): void
    {
        $repository = new SkeletonRepository;
        $project = $this->tmpDir.'/modern-conflict';
        $this->copyDirectory($repository->path(10), $project);

        $kernelPath = $project.'/app/Http/Kernel.php';
        $kernel = file_get_contents($kernelPath);
        self::assertIsString($kernel);
        $kernel = str_replace(
            "\n}\n",
            "\n\n    public function customMiddleware(): void {}\n}\n",
            $kernel,
        );
        self::assertIsInt(file_put_contents($kernelPath, $kernel));
        $before = $this->fileContents($project);
        $collector = new FindingCollector;

        $result = (new SkeletonStep($repository))->syncProject(
            $project,
            10,
            11,
            $collector,
            false,
            'modern',
        );

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['added']);
        self::assertSame([], $result['removed']);
        self::assertSame([], $result['modified']);
        self::assertSame([], $result['renamed']);
        self::assertSame([], $result['deleted']);
        self::assertSame(['app/Http/Kernel.php'], $result['conflicts']);
        self::assertSame($before, $this->fileContents($project));
        self::assertFileDoesNotExist($project.'/bootstrap/providers.php');
    }

    public function test_modern_generic_phpunit_conflict_is_preflighted_before_any_structure_write(): void
    {
        $repository = new SkeletonRepository;
        $project = $this->tmpDir.'/modern-phpunit-conflict';
        $this->copyDirectory($repository->path(10), $project);

        $path = $project.'/phpunit.xml';
        $phpunit = file_get_contents($path);
        self::assertIsString($phpunit);
        $phpunit = str_replace(
            '<env name="CACHE_DRIVER" value="array"/>',
            '<env name="CACHE_DRIVER" value="redis"/>',
            $phpunit,
        );
        self::assertIsInt(file_put_contents($path, $phpunit));
        $before = $this->fileContents($project);

        $result = (new SkeletonStep($repository))->syncProject(
            $project,
            10,
            11,
            new FindingCollector,
            false,
            'modern',
        );

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['added']);
        self::assertSame([], $result['removed']);
        self::assertSame([], $result['modified']);
        self::assertSame([], $result['renamed']);
        self::assertSame([], $result['deleted']);
        self::assertSame(['phpunit.xml'], $result['conflicts']);
        self::assertSame($before, $this->fileContents($project));
        self::assertFileExists($project.'/app/Http/Kernel.php');
        self::assertFileDoesNotExist($project.'/bootstrap/providers.php');
    }

    public function test_custom_legacy_bootstrap_conflict_is_preflighted_before_any_structure_write(): void
    {
        $repository = new SkeletonRepository;
        $project = $this->tmpDir.'/modern-bootstrap-conflict';
        $this->copyDirectory($repository->path(10), $project);

        $path = $project.'/bootstrap/app.php';
        $bootstrap = file_get_contents($path);
        self::assertIsString($bootstrap);
        self::assertIsInt(file_put_contents($path, $bootstrap."\n// project legacy bootstrap customization\n"));
        $before = $this->fileContents($project);

        $result = (new SkeletonStep($repository))->syncProject(
            $project,
            10,
            11,
            new FindingCollector,
            false,
            'modern',
        );

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['added']);
        self::assertSame([], $result['removed']);
        self::assertSame([], $result['modified']);
        self::assertSame([], $result['renamed']);
        self::assertSame([], $result['deleted']);
        self::assertSame(['bootstrap/app.php'], $result['conflicts']);
        self::assertSame($before, $this->fileContents($project));
        self::assertFileExists($project.'/app/Http/Kernel.php');
        self::assertFileDoesNotExist($project.'/bootstrap/providers.php');
    }

    /** @return array<string, string> */
    private function fileContents(string $directory): array
    {
        $contents = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (! $fileInfo->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($directory) + 1));
            $value = file_get_contents($fileInfo->getPathname());
            self::assertIsString($value);
            $contents[$relative] = $value;
        }

        ksort($contents);

        return $contents;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        mkdir($destination, 0777, true);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($source) + 1));
            $destinationPath = $destination.'/'.$relative;

            if ($fileInfo->isDir()) {
                if (! is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }

                continue;
            }

            if (! is_dir(dirname($destinationPath))) {
                mkdir(dirname($destinationPath), 0777, true);
            }
            self::assertTrue(copy($fileInfo->getPathname(), $destinationPath));
            chmod($destinationPath, fileperms($fileInfo->getPathname()) & 0777);
        }
    }
}
