<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateImageValidationSvgRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 12: the image validation rule no longer allows SVG files by default';

    public function getNodeTypes(): array
    {
        return [ClassMethod::class, Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof ClassMethod) {
            return $this->refactorClassMethod($node);
        }

        if ($node instanceof Expression) {
            return $this->refactorExpression($node);
        }

        return null;
    }

    private function refactorClassMethod(ClassMethod $classMethod): ?ClassMethod
    {
        if (! $this->isName($classMethod->name, 'rules')) {
            return null;
        }

        if ($this->hasUpgradeComment($classMethod)) {
            return null;
        }

        if (! $this->containsSvgSensitiveRulesMethod($classMethod)) {
            return null;
        }

        $classMethod->setAttribute('comments', array_merge([
            new Comment('// ' . self::COMMENT_MARKER . '. Add image:allow_svg or File::image(allowSvg: true) if your application relied on SVG uploads.'),
        ], $classMethod->getComments()));
        $classMethod->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $classMethod;
    }

    private function refactorExpression(Expression $expression): ?Expression
    {
        if ($this->hasUpgradeComment($expression)) {
            return null;
        }

        if (! $this->containsSvgSensitiveValidationCall($expression)) {
            return null;
        }

        $expression->setAttribute('comments', array_merge([
            new Comment('// ' . self::COMMENT_MARKER . '. Add image:allow_svg or File::image(allowSvg: true) if your application relied on SVG uploads.'),
        ], $expression->getComments()));
        $expression->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $expression;
    }

    private function containsSvgSensitiveRulesMethod(ClassMethod $classMethod): bool
    {
        if ($classMethod->stmts === null) {
            return false;
        }

        foreach ($classMethod->stmts as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Array_ && $this->containsSvgSensitiveValidationArray($stmt->expr)) {
                return true;
            }
        }

        return false;
    }

    private function containsSvgSensitiveValidationCall(Expression $expression): bool
    {
        $expr = $expression->expr;

        if ($expr instanceof Assign) {
            $expr = $expr->expr;
        }

        if ($expr instanceof MethodCall) {
            $methodName = $this->getName($expr->name);

            if (in_array($methodName, ['validate', 'validateWithBag'], true)) {
                foreach ($expr->args as $arg) {
                    if ($arg instanceof Arg && $arg->value instanceof Array_ && $this->containsSvgSensitiveValidationArray($arg->value)) {
                        return true;
                    }
                }
            }
        }

        if (! $expr instanceof StaticCall) {
            return false;
        }

        if (! $this->isValidatorMakeCall($expr)) {
            return false;
        }

        if (! isset($expr->args[1]) || ! $expr->args[1] instanceof Arg || ! $expr->args[1]->value instanceof Array_) {
            return false;
        }

        return $this->containsSvgSensitiveValidationArray($expr->args[1]->value);
    }

    private function containsSvgSensitiveValidationArray(Array_ $validationArray): bool
    {
        foreach ($validationArray->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if ($item->value instanceof String_ && $this->containsStandaloneImageRule($item->value->value)) {
                return true;
            }

            if ($item->value instanceof StaticCall && $this->isUnconfiguredFileImageCall($item->value)) {
                return true;
            }

            if ($item->value instanceof Array_ && $this->containsSvgSensitiveRulesList($item->value)) {
                return true;
            }
        }

        return false;
    }

    private function containsSvgSensitiveRulesList(Array_ $rulesList): bool
    {
        foreach ($rulesList->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if ($item->value instanceof String_ && $this->containsStandaloneImageRule($item->value->value)) {
                return true;
            }

            if ($item->value instanceof StaticCall && $this->isUnconfiguredFileImageCall($item->value)) {
                return true;
            }
        }

        return false;
    }

    private function containsStandaloneImageRule(string $value): bool
    {
        if (preg_match('/\bimage:[a-z_]+/', $value)) {
            return false;
        }

        if (preg_match('/(?:mimes|mimetypes):[^|]*\bimage\b/', $value)) {
            return false;
        }

        return preg_match('/(?<!\w)image(?!:)(?!\w)/', $value) === 1;
    }

    private function isValidatorMakeCall(StaticCall $staticCall): bool
    {
        $className = $this->getName($staticCall->class);
        $isValidator = $className === 'Validator' ||
            $className === 'Illuminate\\Support\\Facades\\Validator' ||
            str_ends_with((string) $className, '\\Validator');

        return $isValidator && $this->isName($staticCall->name, 'make');
    }

    private function isUnconfiguredFileImageCall(StaticCall $staticCall): bool
    {
        if (! $this->isName($staticCall->name, 'image')) {
            return false;
        }

        if (! $staticCall->class instanceof Name) {
            return false;
        }

        if (! $this->isName($staticCall->class, 'File') && ! $this->isName($staticCall->class, 'Illuminate\\Validation\\Rules\\File')) {
            return false;
        }

        foreach ($staticCall->args as $arg) {
            if (! $arg instanceof Arg || ! $arg->name instanceof Identifier) {
                continue;
            }

            if ($this->isName($arg->name, 'allowSvg') && $this->isTrueLiteral($arg->value)) {
                return false;
            }
        }

        return true;
    }

    private function isTrueLiteral(Node $node): bool
    {
        return $node instanceof ConstFetch && strtolower($this->getName($node->name) ?? '') === 'true';
    }

    private function hasUpgradeComment(Node $node): bool
    {
        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comments when image validation rules may have relied on Laravel 11 SVG behavior',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
'photo' => 'required|image'
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
// Laravel 12: the image validation rule no longer allows SVG files by default. Add image:allow_svg or File::image(allowSvg: true) if your application relied on SVG uploads.
'photo' => 'required|image'
CODE_SAMPLE
                ),
            ],
        );
    }
}
