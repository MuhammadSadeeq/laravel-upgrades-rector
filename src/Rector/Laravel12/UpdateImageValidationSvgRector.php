<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateImageValidationSvgRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade image-rule-svg';

    public function __construct(
        private readonly CommentInserter $commentInserter,
    ) {}

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

        if (! $this->containsSvgSensitiveNode($classMethod)) {
            return null;
        }

        if (! $this->commentInserter->addComment(
            $classMethod,
            self::COMMENT_MARKER,
            'the image validation rule no longer allows SVG files by default. Add image:allow_svg or File::image(allowSvg: true) if your application relied on SVG uploads.'
        )) {
            return null;
        }

        return $classMethod;
    }

    private function refactorExpression(Expression $expression): ?Expression
    {
        if (! $this->containsSvgSensitiveValidationCall($expression)) {
            return null;
        }

        if (! $this->commentInserter->addComment(
            $expression,
            self::COMMENT_MARKER,
            'the image validation rule no longer allows SVG files by default. Add image:allow_svg or File::image(allowSvg: true) if your application relied on SVG uploads.'
        )) {
            return null;
        }

        return $expression;
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
                    if ($arg instanceof Arg && $this->containsSvgSensitiveNode($arg->value)) {
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

        if (! isset($expr->args[1]) || ! $expr->args[1] instanceof Arg) {
            return false;
        }

        return $this->containsSvgSensitiveNode($expr->args[1]->value);
    }

    private function containsSvgSensitiveNode(Node $node): bool
    {
        $containsSensitiveNode = false;

        $this->traverseNodesWithCallable($node, function (Node $subNode) use (&$containsSensitiveNode): ?int {
            if ($subNode instanceof String_ && $this->containsStandaloneImageRule($subNode->value)) {
                $containsSensitiveNode = true;

                return 1;
            }

            if ($subNode instanceof StaticCall && $this->isUnconfiguredFileImageCall($subNode)) {
                $containsSensitiveNode = true;

                return 1;
            }

            return null;
        });

        return $containsSensitiveNode;
    }

    private function containsStandaloneImageRule(string $value): bool
    {
        foreach (explode('|', $value) as $segment) {
            $segment = strtolower(trim($segment));

            if ($segment === '') {
                continue;
            }

            if ($segment === 'image') {
                return true;
            }
        }

        return false;
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

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comments when image validation rules may have relied on Laravel 11 SVG behavior',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$rules = ['photo' => 'required|image'];
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
// Laravel 12: the image validation rule no longer allows SVG files by default. Add image:allow_svg or File::image(allowSvg: true) if your application relied on SVG uploads.
$rules = ['photo' => 'required|image'];
CODE_SAMPLE
                ),
            ],
        );
    }
}
