<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Advisory;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory\FindingAnnotator;
use PHPUnit\Framework\TestCase;

final class FindingAnnotatorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/finding-annotator-'.uniqid('', true);
        mkdir($this->root.'/app', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/app/*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($this->root.'/app');
        @rmdir($this->root);
    }

    public function test_inserts_an_indented_marker_and_is_idempotent(): void
    {
        $path = $this->root.'/app/Example.php';
        file_put_contents($path, "<?php\nclass Example\n{\n    public function run(): void\n    {\n    }\n}\n");
        $annotator = new FindingAnnotator($this->root);

        self::assertTrue($annotator->annotate($path, 4, 'laravelUpgrade.example', "Review this\nmessage."));
        $annotated = (string) file_get_contents($path);
        self::assertStringContainsString("    // TODO(laravel-upgrade:laravelUpgrade.example): Review this message.\n    public function run(): void", $annotated);

        self::assertFalse($annotator->annotate($path, 4, 'laravelUpgrade.example', 'same marker'));
        self::assertSame($annotated, file_get_contents($path));
    }

    public function test_preserves_crlf_and_rejects_unsafe_targets(): void
    {
        $path = $this->root.'/app/Windows.php';
        $contents = "<?php\r\nclass Windows\r\n{\r\n    public function run(): void\r\n    {\r\n    }\r\n}\r\n";
        file_put_contents($path, $contents);
        $annotator = new FindingAnnotator($this->root);

        self::assertTrue($annotator->annotate($path, 4, 'laravelUpgrade.crlf', "one\ntwo"));
        $annotated = (string) file_get_contents($path);
        self::assertStringNotContainsString("\n", str_replace("\r\n", '', $annotated));
        self::assertStringContainsString("    // TODO(laravel-upgrade:laravelUpgrade.crlf): one two\r\n", $annotated);

        $textPath = $this->root.'/app/notes.txt';
        file_put_contents($textPath, "not PHP\n");
        $outside = sys_get_temp_dir().'/finding-annotator-outside-'.uniqid('', true).'.php';
        file_put_contents($outside, "<?php\n// outside\n");

        self::assertFalse($annotator->annotate($textPath, 1, 'rule', 'no'));
        self::assertFalse($annotator->annotate($path, 0, 'rule', 'no'));
        self::assertFalse($annotator->annotate($this->root.'/missing.php', 1, 'rule', 'no'));
        self::assertFalse($annotator->annotate($outside, 2, 'rule', 'no'));
        self::assertSame("<?php\n// outside\n", file_get_contents($outside));
        @unlink($outside);
        @unlink($textPath);
    }

    public function test_batch_annotates_same_rule_at_multiple_lines_and_is_idempotent(): void
    {
        $path = $this->root.'/app/Multiple.php';
        file_put_contents($path, "<?php\nclass Multiple\n{\n    public function first(): void {}\n    public function second(): void {}\n}\n");
        $findings = [
            ['file' => $path, 'line' => 4, 'ruleId' => 'laravelUpgrade.sameRule', 'message' => 'first finding'],
            ['file' => $path, 'line' => 5, 'ruleId' => 'laravelUpgrade.sameRule', 'message' => 'second finding'],
        ];
        $annotator = new FindingAnnotator($this->root);

        self::assertSame(2, $annotator->annotateBatch($findings));
        $annotated = (string) file_get_contents($path);
        self::assertSame(2, substr_count($annotated, '// TODO(laravel-upgrade:laravelUpgrade.sameRule):'));
        self::assertStringContainsString('first finding', $annotated);
        self::assertStringContainsString('second finding', $annotated);
        self::assertSame(0, $annotator->annotateBatch($findings));
        self::assertSame($annotated, file_get_contents($path));
    }

    public function test_invalid_rule_id_uses_a_non_empty_marker_fallback(): void
    {
        $path = $this->root.'/app/Fallback.php';
        file_put_contents($path, "<?php\nclass Fallback {}\n");

        self::assertTrue((new FindingAnnotator($this->root))->annotate($path, 2, "\n", 'review'));
        self::assertStringContainsString('// TODO(laravel-upgrade:finding): review', (string) file_get_contents($path));
    }
}
