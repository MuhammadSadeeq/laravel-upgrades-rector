<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Support;

use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionNamedType;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Every getRuleDefinition() code sample must parse as PHP.
 * This gate keeps them fixed — every code sample of every rule must parse
 * as PHP (optionally wrapped in an opening tag).
 */
final class RuleSamplesParseTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    public function test_every_rule_code_sample_parses_as_php(): void
    {
        $ruleFiles = $this->findRuleFiles();
        self::assertGreaterThan(20, count($ruleFiles), 'No rule files discovered — glob is broken.');

        $failures = [];
        $sampleCount = 0;

        foreach ($ruleFiles as $file) {
            $className = $this->classNameFromFile($file);

            if ($className === null || ! class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            if (! $reflection->isInstantiable()
                || ! $reflection->hasMethod('getRuleDefinition')
                || $reflection->isAbstract()) {
                continue;
            }

            try {
                $rule = $this->instantiate($reflection);

                // getRuleDefinition() comes from Rector's AbstractRector; the
                // interface contract is docblock-only in this Rector version.
                if (! method_exists($rule, 'getRuleDefinition')) {
                    continue;
                }

                /** @var RuleDefinition $definition */
                $definition = $rule->getRuleDefinition();
            } catch (\Throwable) {
                // Rules needing services they cannot get in isolation are
                // covered by their fixture suites instead.
                continue;
            }

            foreach ($this->extractSamples($definition) as $index => $code) {
                $sampleCount++;

                if ($this->parses($code)) {
                    continue;
                }

                $failures[] = sprintf('%s sample #%d does not parse: %s', $className, $index + 1, substr($code, 0, 60));
            }
        }

        self::assertSame([], $failures, implode("\n", $failures));
        self::assertGreaterThan(40, $sampleCount, 'Suspiciously few samples collected.');
    }

    /**
     * @return list<string>
     */
    private function extractSamples(RuleDefinition $definition): array
    {
        $codes = [];

        foreach ($definition->getCodeSamples() as $codeSample) {
            if ($codeSample instanceof CodeSample) {
                $codes[] = $codeSample->getBadCode();
                $codes[] = $codeSample->getGoodCode();
            } elseif ($codeSample instanceof ConfiguredCodeSample) {
                $codes[] = $codeSample->getBadCode();
                $codes[] = $codeSample->getGoodCode();
            }
        }

        return $codes;
    }

    private function parses(string $code): bool
    {
        $trimmed = trim($code);

        if ($trimmed === '') {
            return false;
        }

        $toLint = str_starts_with($trimmed, '<?php') ? $code : "<?php\n".$code;

        try {
            $this->parser->parse($toLint);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function findRuleFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__.'/../../src/Rector')
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function classNameFromFile(string $file): ?string
    {
        $contents = (string) file_get_contents($file);

        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $ns) !== 1
            || preg_match('/^final\s+class\s+(\w+)/m', $contents, $cls) !== 1) {
            return null;
        }

        return $ns[1].'\\'.$cls[1];
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     */
    private function instantiate(ReflectionClass $reflection): object
    {
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $typeName = $type->getName();

                if (class_exists($typeName)) {
                    $arguments[] = $this->instantiate(new ReflectionClass($typeName));

                    continue;
                }
            }

            throw new \RuntimeException(sprintf('Cannot autowire $%s.', $parameter->getName()));
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
