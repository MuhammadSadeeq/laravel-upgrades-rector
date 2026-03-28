<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdatePassportRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Class_::class, StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        // Handle AppServiceProvider boot method to add password grant documentation
        if ($node instanceof Class_) {
            return $this->handleAppServiceProvider($node);
        }

        // Handle Passport static calls
        if ($node instanceof StaticCall) {
            return $this->handlePassportCalls($node);
        }

        return null;
    }

    private function handleAppServiceProvider(Class_ $class): ?Node
    {
        // Check if this is AppServiceProvider
        if (!$class->name || $class->name->name !== "AppServiceProvider") {
            return null;
        }

        // Look for boot method
        foreach ($class->stmts as $stmt) {
            if (
                $stmt instanceof ClassMethod &&
                $this->isName($stmt->name, "boot")
            ) {
                // Add documentation about password grant
                $stmt->setAttribute("comments", [
                    new \PhpParser\Comment\Doc(
                        "/** Laravel 11 Passport 12: Password grant type is disabled by default. " .
                            "Add Passport::enablePasswordGrant() in this method if you need password grants. */",
                    ),
                ]);
                return $class;
            }
        }

        return null;
    }

    private function handlePassportCalls(StaticCall $staticCall): ?Node
    {
        if (!$this->isName($staticCall->class, "Passport")) {
            return null;
        }

        $methodName = $this->getName($staticCall->name);

        // Document Passport method calls that might be affected
        if (
            in_array(
                $methodName,
                ["routes", "tokensExpireIn", "refreshTokensExpireIn"],
                true,
            )
        ) {
            $staticCall->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Laravel 11 Passport 12: Migrations are no longer auto-loaded. " .
                        "Run: php artisan vendor:publish --tag=passport-migrations */",
                ),
            ]);
            return $staticCall;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Update Passport configuration for Laravel 11 compatibility",
            [
                new CodeSample(
                    'class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // existing code
    }
}',
                    '/** Laravel 11 Passport 12: Password grant type is disabled by default. Add Passport::enablePasswordGrant() in this method if you need password grants. */
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // existing code
    }
}',
                ),
                new CodeSample(
                    "Passport::routes()",
                    '/** Laravel 11 Passport 12: Migrations are no longer auto-loaded. Run: php artisan vendor:publish --tag=passport-migrations */
Passport::routes()',
                ),
            ],
        );
    }
}
