<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateMailerContractRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Class_) {
            return null;
        }

        // Check if this class implements Mailer contract
        $implementsMailer = false;
        if ($node->implements) {
            foreach ($node->implements as $implement) {
                if (
                    $this->isName(
                        $implement,
                        "Illuminate\Contracts\Mail\Mailer",
                    )
                ) {
                    $implementsMailer = true;
                    break;
                }
            }
        }

        if (!$implementsMailer) {
            return null;
        }

        // Check if the class already has the sendNow method
        $hasSendNowMethod = false;
        foreach ($node->stmts as $stmt) {
            if (
                $stmt instanceof ClassMethod &&
                $this->isName($stmt->name, "sendNow")
            ) {
                $hasSendNowMethod = true;
                break;
            }
        }

        // If it doesn't have the method, add documentation
        if (!$hasSendNowMethod) {
            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Laravel 11: Mailer contract requires new sendNow method. " .
                        'Add: public function sendNow($mailable, array $data = [], $callback = null); */',
                ),
            ]);
            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation for missing sendNow method in Mailer implementations for Laravel 11",
            [
                new CodeSample(
                    'class CustomMailer implements Mailer
{
    // existing methods...
}',
                    '/** Laravel 11: Mailer contract requires new sendNow method. Add: public function sendNow($mailable, array $data = [], $callback = null); */
class CustomMailer implements Mailer
{
    // existing methods...
}',
                ),
            ],
        );
    }
}
