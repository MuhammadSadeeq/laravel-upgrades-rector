<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateEmailVerificationSetupRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade email-verification-setup';

    public function __construct(
        private readonly CommentInserter $commentInserter,
    ) {}

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        $scope = $node->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return null;
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        if (! $classReflection->is('Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider')) {
            return null;
        }

        if ($this->hasMethod($node, 'configureEmailVerification')) {
            return null;
        }

        if ($this->registersRegisteredListener($node)) {
            return null;
        }

        if (! $this->commentInserter->addComment(
            $node,
            self::COMMENT_MARKER,
            'EventServiceProvider now auto-registers SendEmailVerificationNotification. Define configureEmailVerification() if you want to opt out of the automatic listener registration.'
        )) {
            return null;
        }

        return $node;
    }

    private function hasMethod(Class_ $class, string $methodName): bool
    {
        foreach ($class->getMethods() as $method) {
            if ($this->isName($method->name, $methodName)) {
                return true;
            }
        }

        return false;
    }

    private function registersRegisteredListener(Class_ $class): bool
    {
        foreach ($class->getProperties() as $property) {
            if (! $property instanceof Property || ! $this->isName($property, 'listen')) {
                continue;
            }

            $default = $property->props[0]->default;

            if (! $default instanceof Array_) {
                continue;
            }

            foreach ($default->items as $item) {
                if (! $item instanceof ArrayItem) {
                    continue;
                }

                if ($item->key === null) {
                    continue;
                }

                if ($this->isRegisteredEventKey($item->key)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isRegisteredEventKey(Node $node): bool
    {
        if ($node instanceof String_) {
            return $node->value === 'Illuminate\\Auth\\Events\\Registered';
        }

        if (! $node instanceof ClassConstFetch) {
            return false;
        }

        if (! $node->class instanceof Name) {
            return false;
        }

        return $this->isName($node->class, 'Illuminate\\Auth\\Events\\Registered') && $this->isName($node->name, 'class');
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Warn when EventServiceProvider relies on Laravel 10 email verification registration semantics',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
// Laravel 11: EventServiceProvider now auto-registers SendEmailVerificationNotification. Define configureEmailVerification() if you want to opt out of the automatic listener registration.
class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
}
CODE_SAMPLE
                ),
            ]
        );
    }
}
