<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\MergeIfMissingDotKeysRule;

/** @extends Laravel12RuleTestCase<MergeIfMissingDotKeysRule> */
final class MergeIfMissingDotKeysRuleTest extends Laravel12RuleTestCase
{
    protected function getRule(): MergeIfMissingDotKeysRule
    {
        return new MergeIfMissingDotKeysRule;
    }

    public function test_request_nested_key_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/merge-if-missing-positive.php'], [[
            'mergeIfMissing() now supports dot notation for nested array merging.',
            9,
            'Verify your code handles the new nested merging behaviour.',
        ]]);
    }

    public function test_request_top_level_key_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/merge-if-missing-safe.php'], []);
    }

    public function test_unrelated_receiver_with_a_dotted_key_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/merge-if-missing-unrelated.php'], []);
    }
}
