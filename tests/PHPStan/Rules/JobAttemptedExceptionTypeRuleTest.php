<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\JobAttemptedExceptionTypeRule;

/** @extends Laravel13RuleTestCase<JobAttemptedExceptionTypeRule> */
final class JobAttemptedExceptionTypeRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): JobAttemptedExceptionTypeRule
    {
        return new JobAttemptedExceptionTypeRule;
    }

    public function test_boolean_comparisons_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/job-attempted-comparisons.php'], [
            [
                'JobAttempted exception is now ?Throwable in Laravel 13, not a boolean.',
                7,
                'Use $event->successful() for a boolean result or handle the Throwable|null exception explicitly.',
            ],
            [
                'JobAttempted exception is now ?Throwable in Laravel 13, not a boolean.',
                8,
                'Use $event->successful() for a boolean result or handle the Throwable|null exception explicitly.',
            ],
        ]);
    }

    public function test_boolean_assignment_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/job-attempted-assignment.php'], [[
            'JobAttempted exception is now ?Throwable in Laravel 13, not a boolean.',
            7,
            'Use $event->successful() for a boolean result or handle the Throwable|null exception explicitly.',
        ]]);
    }

    public function test_explicit_nullable_exception_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/job-attempted-safe.php'], []);
    }

    public function test_integer_and_string_assignments_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/job-attempted-scalar-assignments.php'], [
            [
                'JobAttempted exception is now ?Throwable in Laravel 13, not a boolean.',
                7,
                'Use $event->successful() for a boolean result or handle the Throwable|null exception explicitly.',
            ],
            [
                'JobAttempted exception is now ?Throwable in Laravel 13, not a boolean.',
                8,
                'Use $event->successful() for a boolean result or handle the Throwable|null exception explicitly.',
            ],
        ]);
    }
}
