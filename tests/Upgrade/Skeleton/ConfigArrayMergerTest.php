<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\ConfigArrayMerger;
use PHPUnit\Framework\TestCase;

final class ConfigArrayMergerTest extends TestCase
{
    private ConfigArrayMerger $merger;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->merger = new ConfigArrayMerger;
        $this->tmpDir = sys_get_temp_dir().'/cfg-merge-'.uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
    }

    public function test_adds_missing_keys(): void
    {
        $project = $this->write('project.php', "<?php\n\nreturn [\n    'default' => env('DB_CONNECTION', 'sqlite'),\n];\n");
        $upstream = $this->write('upstream.php', "<?php\n\nreturn [\n    'default' => env('DB_CONNECTION', 'sqlite'),\n    'new_key' => 'new_value',\n];\n");

        $merged = $this->merger->merge($project, $upstream);

        self::assertStringContainsString("'new_key' => 'new_value'", $merged);
        self::assertStringContainsString("'default' => env", $merged);
    }

    public function test_does_not_remove_existing_keys(): void
    {
        $project = $this->write('project.php', "<?php\n\nreturn [\n    'custom' => 'value',\n    'default' => 'sqlite',\n];\n");
        $upstream = $this->write('upstream.php', "<?php\n\nreturn [\n    'default' => 'mysql',\n];\n");

        $merged = $this->merger->merge($project, $upstream);

        self::assertStringContainsString("'custom' => 'value'", $merged);
    }

    public function test_find_missing_keys(): void
    {
        $project = $this->write('project.php', "<?php\n\nreturn ['a' => 1];\n");
        $upstream = $this->write('upstream.php', "<?php\n\nreturn ['a' => 1, 'b' => 2, 'c' => 3];\n");

        $missing = $this->merger->findMissingKeys($project, $upstream);

        self::assertSame(['b', 'c'], $missing);
    }

    public function test_merges_nested_keys_and_preserves_preamble_comments(): void
    {
        $project = $this->write('project.php', "<?php\n\n// Project configuration.\nreturn [\n    'connections' => [\n        'sqlite' => [\n            'driver' => 'sqlite',\n        ],\n    ],\n];\n");
        $upstream = $this->write('upstream.php', "<?php\n\nreturn [\n    'connections' => [\n        'sqlite' => [\n            'driver' => 'sqlite',\n            'busy_timeout' => null,\n        ],\n    ],\n];\n");

        $merged = $this->merger->merge($project, $upstream);

        self::assertStringContainsString('// Project configuration.', $merged);
        self::assertStringContainsString("'busy_timeout' => null", $merged);
    }

    public function test_insert_only_merge_preserves_nested_comments_blank_lines_and_custom_order(): void
    {
        $project = $this->write('project.php', "<?php\n\nreturn [\n    // Keep the connection comment.\n    'connections' => [\n        'sqlite' => [\n            // Keep this nested comment.\n            'driver' => 'sqlite',\n\n            // Keep the custom key after the upstream slot.\n            'custom' => true,\n        ],\n    ],\n    'tail' => true,\n];\n");
        $upstream = $this->write('upstream.php', "<?php\nreturn [\n    'connections' => [\n        'sqlite' => [\n            'driver' => 'sqlite',\n            'busy_timeout' => null,\n        ],\n    ],\n    'tail' => true,\n];\n");

        $merged = $this->merger->merge($project, $upstream);

        self::assertStringContainsString("    // Keep the connection comment.\n", $merged);
        self::assertStringContainsString("            // Keep this nested comment.\n", $merged);
        self::assertStringContainsString("\n\n            // Keep the custom key after the upstream slot.\n", $merged);
        self::assertStringContainsString("'busy_timeout' => null", $merged);
        self::assertStringContainsString("'custom' => true", $merged);
        $busyPosition = strpos($merged, "'busy_timeout' => null");
        $customPosition = strpos($merged, "'custom' => true");
        self::assertIsInt($busyPosition);
        self::assertIsInt($customPosition);
        self::assertLessThan($customPosition, $busyPosition);
    }

    public function test_policy_preserves_previous_value_and_reports_behaviour_change(): void
    {
        $project = $this->write('session.php', "<?php\nreturn [];\n");
        $upstream = $this->write('target.php', "<?php\nreturn ['serialization' => 'json'];\n");
        $collector = new FindingCollector;

        $merged = $this->merger->merge($project, $upstream, $collector, 13);

        self::assertStringContainsString("'serialization' => 'php'", $merged);
        self::assertCount(1, $collector->all());
        self::assertSame('laravelUpgrade.configBehaviourChange', $collector->all()[0]->ruleId);
        self::assertSame('high', $collector->all()[0]->severity);
    }

    public function test_three_way_config_merge_reports_removed_and_changed_defaults_without_replacing_project_values(): void
    {
        $project = $this->write('project.php', "<?php\nreturn [\n    'keep' => 'custom',\n    'removed' => 'project',\n    'changed' => 'custom',\n];\n");
        $base = $this->write('base.php', "<?php\nreturn [\n    'keep' => 'base',\n    'removed' => 'base',\n    'changed' => 'old',\n];\n");
        $upstream = $this->write('upstream.php', "<?php\nreturn [\n    'keep' => 'base',\n    'changed' => 'new',\n];\n");
        $collector = new FindingCollector;

        $merged = $this->merger->mergeWithBase($project, $base, $upstream, $collector, 12);

        self::assertStringContainsString("'removed' => 'project'", $merged);
        self::assertStringContainsString("'changed' => 'custom'", $merged);
        self::assertSame(
            ['laravelUpgrade.configKeyRemoved', 'laravelUpgrade.configDefaultChanged'],
            array_map(static fn ($finding): string => $finding->ruleId, $collector->all()),
        );
    }

    public function test_three_way_merge_applies_session_policy_and_preserves_existing_value(): void
    {
        $project = $this->write('session.php', "<?php\nreturn [\n    'serialization' => 'php',\n];\n");
        $base = $this->write('base-session.php', "<?php\nreturn [\n    'serialization' => 'php',\n];\n");
        $upstream = $this->write('target-session.php', "<?php\nreturn [\n    'serialization' => 'json',\n];\n");
        $collector = new FindingCollector;

        $merged = $this->merger->mergeWithBase($project, $base, $upstream, $collector, 13);

        self::assertStringContainsString("'serialization' => 'php'", $merged);
        self::assertCount(1, $collector->all());
        self::assertSame('high', $collector->all()[0]->severity);
        self::assertSame('#session-serialization', $collector->all()[0]->guideUrl);
    }

    public function test_new_policy_key_uses_policy_metadata_instead_of_hardcoded_finding_text(): void
    {
        $project = $this->write('cache.php', "<?php\nreturn [];\n");
        $upstream = $this->write('target-cache.php', "<?php\nreturn ['serializable_classes' => false];\n");
        $collector = new FindingCollector;

        $this->merger->merge($project, $upstream, $collector, 13);
        $finding = $collector->all()[0] ?? null;

        self::assertNotNull($finding);
        self::assertSame('medium', $finding->severity);
        self::assertStringContainsString('disables PHP object unserialization', $finding->message);
        self::assertStringContainsString('permissive cache unserialization', $finding->action);
        self::assertSame('#cache-serializable-classes', $finding->guideUrl);
    }

    public function test_unscoped_legacy_policy_key_cannot_leak_into_another_config_file(): void
    {
        $policyDirectory = $this->tmpDir.'/policies';
        mkdir($policyDirectory, 0777, true);
        file_put_contents($policyDirectory.'/13.json', json_encode([
            'behaviourChanging' => [
                'serialization' => [
                    'preserveValue' => 'php',
                    'severity' => 'high',
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        $merger = new ConfigArrayMerger($policyDirectory);
        $project = $this->write('session.php', "<?php\nreturn [];\n");
        $upstream = $this->write('target-session.php', "<?php\nreturn ['serialization' => 'json'];\n");
        $collector = new FindingCollector;

        $merged = $merger->merge($project, $upstream, $collector, 13);

        self::assertStringContainsString("'serialization' => 'json'", $merged);
        self::assertCount(0, $collector->all());
    }

    public function test_unchanged_default_does_not_emit_an_informational_finding_without_transition_policy(): void
    {
        $policyDirectory = $this->tmpDir.'/policies';
        mkdir($policyDirectory, 0777, true);
        file_put_contents($policyDirectory.'/11.json', json_encode([
            'behaviourChanging' => [
                'config/cache.php.prefix' => [
                    'informational' => true,
                    'severity' => 'info',
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        $merger = new ConfigArrayMerger($policyDirectory);
        $project = $this->write('cache.php', "<?php\nreturn ['prefix' => 'same'];\n");
        $base = $this->write('base-cache.php', "<?php\nreturn ['prefix' => 'same'];\n");
        $upstream = $this->write('target-cache.php', "<?php\nreturn ['prefix' => 'same'];\n");
        $collector = new FindingCollector;

        $merger->mergeWithBase($project, $base, $upstream, $collector, 11);

        self::assertCount(0, $collector->all());
    }

    public function test_filesystem_root_policy_belongs_to_laravel_11_transition(): void
    {
        $project = $this->write('filesystems.php', "<?php\nreturn ['disks' => ['local' => ['root' => storage_path('app')]]];\n");
        $base = $this->write('base-filesystems.php', "<?php\nreturn ['disks' => ['local' => ['root' => storage_path('app')]]];\n");
        $upstream = $this->write('target-filesystems.php', "<?php\nreturn ['disks' => ['local' => ['root' => storage_path('app/private')]]];\n");
        $collector = new FindingCollector;

        $merged = $this->merger->mergeWithBase($project, $base, $upstream, $collector, 11);

        self::assertStringContainsString("storage_path('app')", $merged);
        self::assertCount(1, $collector->all());
        self::assertSame('medium', $collector->all()[0]->severity);
        self::assertStringContainsString('Laravel 11 changes the local filesystem root', $collector->all()[0]->message);
    }

    public function test_three_way_merge_recurses_into_nested_database_policy(): void
    {
        $project = $this->write('database.php', "<?php\nreturn [\n    'redis' => [\n        'options' => [\n            'prefix' => 'custom-',\n        ],\n    ],\n];\n");
        $base = $this->write('base-database.php', "<?php\nreturn [\n    'redis' => [\n        'options' => [\n            'prefix' => 'old-',\n        ],\n    ],\n];\n");
        $upstream = $this->write('target-database.php', "<?php\nreturn [\n    'redis' => [\n        'options' => [\n            'prefix' => 'new-',\n        ],\n    ],\n];\n");
        $collector = new FindingCollector;

        $merged = $this->merger->mergeWithBase($project, $base, $upstream, $collector, 13);

        self::assertStringContainsString("'prefix' => 'custom-'", $merged);
        self::assertCount(1, $collector->all());
        self::assertSame('laravelUpgrade.configDefaultChanged', $collector->all()[0]->ruleId);
        self::assertSame('info', $collector->all()[0]->severity);
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->tmpDir.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }
}
