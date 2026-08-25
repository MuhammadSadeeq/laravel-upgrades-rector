<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 12: the 'image' validation rule no longer accepts SVG files by
 * default. Flags string-based rule values containing bare 'image'.
 *
 * @implements Rule<MethodCall>
 */
final class ImageRuleExcludesSvgRule implements Rule
{
    private const ERROR_MESSAGE = "The 'image' validation rule no longer accepts SVG files by default.";

    private const ERROR_TIP = "Add 'image:allow_svg' to keep SVG support or use File::image(allowSvg: true).";

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toLowerString();

        if ($methodName !== 'validate' && $methodName !== 'mergeifmissing') {
            return [];
        }

        $found = [];
        $this->scanForImage($node, $found);

        return $found;
    }

    /**
     * @param list<\PHPStan\Rules\IdentifierRuleError> $errors
     */
    private function scanForImage(Node $node, array &$errors): void
    {
        foreach ($node->getSubNodeNames() as $subName) {
            $value = $node->{$subName};

            $children = is_array($value) ? $value : [$value];

            foreach ($children as $child) {
                if ($child instanceof String_ && $this->hasBareImageRule($child->value)) {
                    $errors[] = RuleErrorBuilder::message(self::ERROR_MESSAGE)
                        ->identifier('laravelUpgrade.imageRuleExcludesSvg')
                        ->tip(self::ERROR_TIP)
                        ->build();

                    return; // one error per validate() call is enough
                }

                if ($child instanceof Node) {
                    $this->scanForImage($child, $errors);

                    if ($errors !== []) {
                        return;
                    }
                }
            }
        }
    }

    private function hasBareImageRule(string $ruleString): bool
    {
        foreach (explode('|', $ruleString) as $part) {
            if (trim($part) === 'image') {
                return true;
            }
        }

        return false;
    }
}
