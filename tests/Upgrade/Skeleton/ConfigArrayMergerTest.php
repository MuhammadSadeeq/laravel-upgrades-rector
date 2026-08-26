<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

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

    private function write(string $name, string $contents): string
    {
        $path = $this->tmpDir.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }
}
