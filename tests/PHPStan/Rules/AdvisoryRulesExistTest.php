<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPUnit\Framework\TestCase;

final class AdvisoryRulesExistTest extends TestCase
{
    public function test_all_rule_classes_exist_and_implement_rule(): void
    {
        $rules = glob(__DIR__.'/../../../src/PHPStan/Rules/*.php');

        self::assertIsArray($rules);
        self::assertGreaterThan(2, count($rules), 'No PHPStan rule files found.');

        foreach ($rules as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            preg_match('/final class (\w+)/', $contents, $m);
            $className = $m[1] ?? '';

            self::assertNotSame('', $className, basename($file).' has no final class.');

            $fqcn = 'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\'.$className;

            self::assertTrue(class_exists($fqcn), $fqcn.' does not autoload.');
            self::assertTrue(is_a($fqcn, Rule::class, true), $fqcn.' does not implement Rule.');
        }
    }
}
