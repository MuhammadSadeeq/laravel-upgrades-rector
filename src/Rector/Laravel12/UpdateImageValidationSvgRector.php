<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateImageValidationSvgRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [ClassMethod::class, MethodCall::class, StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        // Check if this is a validation context
        if ($node instanceof ClassMethod) {
            return $this->refactorClassMethod($node);
        }

        if ($node instanceof MethodCall) {
            return $this->refactorMethodCall($node);
        }

        if ($node instanceof StaticCall) {
            return $this->refactorStaticCall($node);
        }

        return null;
    }

    private function refactorClassMethod(ClassMethod $node): ?Node
    {
        // Only process rules() methods
        if (!$this->isName($node->name, 'rules')) {
            return null;
        }

        // Find the return statement and process its array
        $hasChanges = false;
        if ($node->stmts !== null) {
            $this->traverseNodesWithCallable($node->stmts, function (Node $subNode) use (&$hasChanges) {
                if ($subNode instanceof Return_ && $subNode->expr instanceof Array_) {
                    if ($this->processValidationArray($subNode->expr)) {
                        $hasChanges = true;
                    }
                }
                return null;
            });
        }

        return $hasChanges ? $node : null;
    }

    private function refactorMethodCall(MethodCall $node): ?Node
    {
        $methodName = $this->getName($node->name);

        // Check if this is a validation method call
        if (!in_array($methodName, ['validate', 'validateWithBag'], true)) {
            return null;
        }

        // Process the validation rules argument
        $hasChanges = false;
        foreach ($node->args as $arg) {
            if ($arg instanceof Arg && $arg->value instanceof Array_) {
                if ($this->processValidationArray($arg->value)) {
                    $hasChanges = true;
                }
            }
        }

        return $hasChanges ? $node : null;
    }

    private function refactorStaticCall(StaticCall $node): ?Node
    {
        // Check if this is Validator::make() - handle different class reference styles
        $className = $this->getName($node->class);
        $isValidator = $className === 'Validator' ||
                      $className === 'Illuminate\Support\Facades\Validator' ||
                      str_ends_with((string) $className, '\Validator');

        if (!$isValidator) {
            return null;
        }

        $methodName = $this->getName($node->name);
        if ($methodName !== 'make') {
            return null;
        }

        // The rules are typically in the second argument
        $hasChanges = false;
        if (isset($node->args[1]) && $node->args[1] instanceof Arg && $node->args[1]->value instanceof Array_) {
            if ($this->processValidationArray($node->args[1]->value)) {
                $hasChanges = true;
            }
        }

        return $hasChanges ? $node : null;
    }

    private function processValidationArray(Array_ $array): bool
    {
        $hasChanges = false;

        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem) {
                continue;
            }

            // Process string validation rules
            if ($item->value instanceof String_) {
                $newString = $this->processValidationString($item->value);
                if ($newString !== null) {
                    $item->value = $newString;
                    $hasChanges = true;
                }
            }

            // Process array validation rules
            if ($item->value instanceof Array_) {
                if ($this->processValidationRulesArray($item->value)) {
                    $hasChanges = true;
                }
            }
        }

        return $hasChanges;
    }

    private function processValidationRulesArray(Array_ $array): bool
    {
        $hasChanges = false;

        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem || !$item->value instanceof String_) {
                continue;
            }

            $newString = $this->processValidationString($item->value);
            if ($newString !== null) {
                $item->value = $newString;
                $hasChanges = true;
            }
        }

        return $hasChanges;
    }

    private function processValidationString(String_ $node): ?String_
    {
        $value = $node->value;

        // Only process if it contains the standalone 'image' validation rule
        // Must be:
        // 1. Exactly 'image' (whole string)
        // 2. 'image' at start followed by pipe: 'image|...'
        // 3. 'image' in middle with pipes: '...|image|...'
        // 4. 'image' at end with pipe: '...|image'

        // Exclude 'image' that is:
        // 1. Part of another word: 'imaginary', 'multi_image'
        // 2. Inside MIME types: 'mimes:image/jpeg'
        // 3. Already configured: 'image:allow_svg', 'image:deny_svg'

        // Check if 'image' already has configuration
        if (preg_match('/\bimage:[a-z_]+/', $value)) {
            return null;
        }

        // Check if 'image' appears in MIME type context (after 'mimes:' or 'mimetypes:')
        if (preg_match('/(?:mimes|mimetypes):[^|]*\bimage\b/', $value)) {
            return null;
        }

        // Match standalone 'image' validation rule
        // (?<!\w) = not preceded by word character
        // (?!:) = not followed by colon (already configured)
        // (?!\w) = not followed by word character (not part of another word)
        if (preg_match('/(?<!\w)image(?!:)(?!\w)/', $value)) {
            $newValue = preg_replace(
                '/(?<!\w)image(?!:)(?!\w)/',
                'image:allow_svg',
                $value
            );

            if ($newValue !== null && $newValue !== $value) {
                return new String_($newValue);
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Update image validation rules to explicitly allow SVG files, preserving the old SVG acceptance behavior after Laravel 12 changed the image rule to reject SVGs by default",
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
