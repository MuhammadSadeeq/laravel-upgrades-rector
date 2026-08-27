<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\PaginationDefaultViewRule;

/** @extends Laravel13RuleTestCase<PaginationDefaultViewRule> */
final class PaginationDefaultViewRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): PaginationDefaultViewRule
    {
        return new PaginationDefaultViewRule;
    }

    public function test_default_view_literal_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/pagination-default-view.php'], [[
            'Pagination view "pagination::default" was renamed in Laravel 13.',
            5,
            'Use "pagination::bootstrap-3" or configure the published view explicitly.',
        ]]);
    }

    public function test_simple_default_literal_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/pagination-simple-default-view.php'], [[
            'Pagination view "pagination::simple-default" was renamed in Laravel 13.',
            3,
            'Use "pagination::simple-bootstrap-3" or configure the published view explicitly.',
        ]]);
    }

    public function test_other_pagination_view_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/pagination-safe-view.php'], []);
    }
}
