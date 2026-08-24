<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Support;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Plan P1-06: static gates over src/ that encode the plan's hard rules.
 *
 * - no ORIGINAL_NODE nulling (it forces full-class reprint churn, deleting
 *   blank lines and collapsing promoted constructors);
 * - no `new Name('With\Backslashes')` — relative/mangled FQCNs silently
 *   produce wrong ::class resolution in namespaced files; use FullyQualified;
 * - no legacy php-parser AST constructs (Class_::MODIFIER_* constants,
 *   PropertyProperty nodes).
 */
final class CodeStyleGatesTest extends TestCase
{
    public function test_src_has_no_forbidden_patterns(): void
    {
        $violations = [];

        foreach ($this->srcFiles() as $file) {
            $relative = $this->relative($file);
            $lines = file($file->getPathname()) ?: [];
            $inDocblockBuffer = '';

            foreach ($lines as $index => $line) {
                $lineNo = $index + 1;

                if (str_contains($line, 'ORIGINAL_NODE') && str_contains($line, 'null')) {
                    // The only allowed mention is the explanatory docblock in CommentInserter.
                    if (! str_contains($relative, 'CommentInserter.php')) {
                        $violations[] = sprintf(
                            '%s:%d ORIGINAL_NODE must never be nulled (causes reprint churn).',
                            $relative,
                            $lineNo
                        );
                    }
                }

                foreach ($this->backslashNameLiterals($line) as $literal) {
                    $violations[] = sprintf(
                        '%s:%d new Name(\'%s\') — use FullyQualified for namespaced class literals.',
                        $relative,
                        $lineNo,
                        $literal
                    );
                }

                if (preg_match('/Class_::MODIFIER_|PropertyProperty\b/', $line) === 1) {
                    $violations[] = sprintf(
                        '%s:%d legacy AST construct (Class_::MODIFIER_*/PropertyProperty) — use Modifiers::* and PropertyItem.',
                        $relative,
                        $lineNo
                    );
                }
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function test_composer_test_script_covers_every_environment(): void
    {
        $raw = file_get_contents(dirname(__DIR__, 2).'/composer.json');

        if ($raw === false) {
            self::fail('composer.json could not be read.');
        }

        /** @var array{scripts?: array<string, string>} $composer */
        $composer = json_decode($raw, true);
        $scripts = $composer['scripts'] ?? [];

        foreach (['test', 'test-env-11', 'test-env-12', 'test-env-13', 'analyse'] as $required) {
            self::assertArrayHasKey($required, $scripts, "composer script \"$required\" is missing.");
        }

        foreach (['test-env-11' => 'LARAVEL_ENV=11', 'test-env-12' => 'LARAVEL_ENV=12', 'test-env-13' => 'LARAVEL_ENV=13'] as $script => $needle) {
            self::assertArrayHasKey($script, $scripts);
            self::assertStringContainsString($needle, (string) $scripts[$script]);
        }
    }

    /**
     * @return list<string>
     */
    private function backslashNameLiterals(string $line): array
    {
        // new Name('Foo\Bar') / new Name("Foo\Bar") with a backslash literal.
        if (preg_match_all("/new\s+Name\s*\(\s*(['\"])((?:[^'\"]*\\\\)+[^'\"]*)\1/", $line, $matches) !== 1) {
            return [];
        }

        return $matches[2];
    }

    /**
     * @return list<SplFileInfo>
     */
    private function srcFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__.'/../../src')
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        ksort($files);

        return array_values($files);
    }

    private function relative(SplFileInfo|string $path): string
    {
        return str_replace(dirname(__DIR__, 2).'/', '', (string) $path);
    }
}
