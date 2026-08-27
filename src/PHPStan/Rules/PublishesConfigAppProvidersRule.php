<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 11 moved provider registration out of config/app.php. Flags package
 * publishers that still publish an app.php provider list.
 *
 * @implements Rule<Expression>
 */
final class PublishesConfigAppProvidersRule implements Rule
{
    public function getNodeType(): string
    {
        return Expression::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof Expression) {
            return [];
        }

        $finder = new NodeFinder;
        $hasPublish = $finder->findFirst($node->expr, static function (Node $subNode): bool {
            return $subNode instanceof MethodCall
                && $subNode->name instanceof Identifier
                && $subNode->name->toLowerString() === 'publishes';
        }) !== null;

        if (! $hasPublish) {
            return [];
        }

        $hasConfigApp = $finder->findFirst($node->expr, static function (Node $subNode): bool {
            if ($subNode instanceof String_ && $subNode->value === 'config/app.php') {
                return true;
            }

            if (! $subNode instanceof FuncCall || ! $subNode->name instanceof Node\Name
                || $subNode->name->toLowerString() !== 'config_path') {
                return false;
            }

            return isset($subNode->args[0])
                && $subNode->args[0] instanceof Arg
                && $subNode->args[0]->value instanceof String_
                && $subNode->args[0]->value->value === 'app.php';
        }) !== null;

        if (! $hasConfigApp) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'A package publishes config/app.php for provider registration, which conflicts with Laravel 11 bootstrap provider registration.'
            )->identifier('laravelUpgrade.publishesConfigAppProviders')
                ->tip('Use ServiceProvider::addProviderToBootstrapFile() or publish a package-specific config file.')
                ->build(),
        ];
    }
}
