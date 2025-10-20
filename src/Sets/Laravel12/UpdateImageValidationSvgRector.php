<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateImageValidationSvgRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [String_::class, Array_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof String_) {
            return $this->refactorStringValidation($node);
        }

        if ($node instanceof Array_) {
            return $this->refactorArrayValidation($node);
        }

        return null;
    }

    private function refactorStringValidation(String_ $node): ?String_
    {
        $value = $node->value;

        // Look for 'image' validation rule that doesn't already specify SVG handling
        if (preg_match("/\bimage\b(?!:)/", $value)) {
            // Replace 'image' with 'image:allow_svg' to maintain Laravel 11 behavior
            $newValue = preg_replace(
                "/\bimage\b(?!:)/",
                "image:allow_svg",
                $value,
            );

            if ($newValue !== null && $newValue !== $value) {
                return new String_($newValue);
            }
        }

        return null;
    }

    private function refactorArrayValidation(Array_ $node): ?Array_
    {
        $hasChanges = false;

        foreach ($node->items as $item) {
            if (
                !$item instanceof ArrayItem ||
                !$item->value instanceof String_
            ) {
                continue;
            }

            $value = $item->value->value;

            // Check for standalone 'image' rule
            if ($value === "image") {
                $item->value = new String_("image:allow_svg");
                $hasChanges = true;
            }
            // Check for 'image' within pipe-separated rules
            elseif (preg_match("/\bimage\b(?!:)/", $value)) {
                $newValue = preg_replace(
                    "/\bimage\b(?!:)/",
                    "image:allow_svg",
                    $value,
                );
                if ($newValue !== null && $newValue !== $value) {
                    $item->value = new String_($newValue);
                    $hasChanges = true;
                }
            }
        }

        return $hasChanges ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Update image validation rules to explicitly allow SVG files for Laravel 12 compatibility",
            [
                new CodeSample(
                    "'photo' => 'required|image'",
                    "'photo' => 'required|image:allow_svg'",
                ),
                new CodeSample(
                    "'photo' => ['required', 'image']",
                    "'photo' => ['required', 'image:allow_svg']",
                ),
            ],
        );
    }
}
