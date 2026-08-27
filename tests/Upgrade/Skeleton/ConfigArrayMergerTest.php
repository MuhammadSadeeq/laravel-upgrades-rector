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

    private function write(string $name, string $contents): string
    {
        $path = $this->tmpDir.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }
}
