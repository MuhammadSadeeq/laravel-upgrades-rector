<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\ImageRuleExcludesSvgRule;

/** @extends Laravel12RuleTestCase<ImageRuleExcludesSvgRule> */
final class ImageRuleExcludesSvgRuleTest extends Laravel12RuleTestCase
{
    protected function getRule(): ImageRuleExcludesSvgRule
    {
        return new ImageRuleExcludesSvgRule;
    }

    public function test_bare_image_validation_rule_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/image-rule-positive.php'], [
            [
                "The 'image' validation rule no longer accepts SVG files by default.",
                11,
                "Add 'image:allow_svg' to keep SVG support or use File::image(allowSvg: true).",
            ],
            [
                "The 'image' validation rule no longer accepts SVG files by default.",
                15,
                "Add 'image:allow_svg' to keep SVG support or use File::image(allowSvg: true).",
            ],
            [
                "The 'image' validation rule no longer accepts SVG files by default.",
                19,
                "Add 'image:allow_svg' to keep SVG support or use File::image(allowSvg: true).",
            ],
            [
                "The 'image' validation rule no longer accepts SVG files by default.",
                20,
                "Add 'image:allow_svg' to keep SVG support or use File::image(allowSvg: true).",
            ],
            [
                "The 'image' validation rule no longer accepts SVG files by default.",
                21,
                "Add 'image:allow_svg' to keep SVG support or use File::image(allowSvg: true).",
            ],
        ]);
    }

    public function test_explicit_svg_opt_in_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/image-rule-safe.php'], []);
    }

    public function test_non_image_segments_and_other_methods_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/image-rule-edge.php'], []);
    }
}
