<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\SpatialGeographyReviewRule;

/** @extends Laravel11RuleTestCase<SpatialGeographyReviewRule> */
final class SpatialGeographyReviewRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): SpatialGeographyReviewRule
    {
        return new SpatialGeographyReviewRule;
    }

    public function test_geometry_column_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/spatial-geometry-positive.php'], [[
            'Review whether geography() with an SRID is more appropriate than geometry() for this column.',
            9,
            'geography() uses WGS84 (SRID 4326) by default and handles spherical calculations.',
        ]]);
    }

    public function test_geography_column_is_reported_for_review(): void
    {
        $this->analyse([__DIR__.'/Fixture/spatial-geography-positive.php'], [[
            'Review whether geography() with an SRID is more appropriate than geometry() for this column.',
            9,
            'geography() uses WGS84 (SRID 4326) by default and handles spherical calculations.',
        ]]);
    }

    public function test_unrelated_receiver_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/spatial-safe.php'], []);
    }
}
