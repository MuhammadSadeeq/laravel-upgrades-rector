<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VarLikeIdentifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdatePasswordRehashingRector extends AbstractRector
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

        // Check if this class uses the Authenticatable trait or extends User model
        $usesAuthenticatable = false;
        $isUserModel = false;

        // Check if class name suggests it's a User model
        if ($node->name && in_array($node->name->name, ['User'], true)) {
            $isUserModel = true;
        }

        // Check for Authenticatable trait usage
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\TraitUse) {
                foreach ($stmt->traits as $trait) {
                    if ($this->isName($trait, 'Illuminate\Auth\Authenticatable')) {
                        $usesAuthenticatable = true;
                        break;
                    }
                }
            }
        }

        if (!$usesAuthenticatable && !$isUserModel) {
            return null;
        }

        // Check if the class has a custom password field and add documentation
        $hasCustomPasswordField = false;
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Property) {
                foreach ($stmt->props as $prop) {
                    if ($prop instanceof PropertyProperty) {
                        // Look for properties that might be password fields (not named 'password')
                        $propertyName = $prop->name->name;
                        if (str_contains($propertyName, 'password') && $propertyName !== 'password') {
                            $hasCustomPasswordField = true;
                        }
                    }
                }
            }
        }

        // Add documentation comment about password rehashing
        if ($hasCustomPasswordField || $isUserModel) {
            $node->setAttribute('comments', [
                new \PhpParser\Comment\Doc(
                    '/** Laravel 11: Auto password rehashing enabled. ' .
                    'If using custom password field name, add protected $authPasswordName = \'custom_field_name\'; ' .
                    'To disable: set rehash_on_login => false in config/hashing.php */'
                )
            ]);
            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add documentation for password rehashing changes in Laravel 11',
            [
                new CodeSample(
                    'class User extends Authenticatable
{
    protected $custom_password_field;
}',
                    '/** Laravel 11: Auto password rehashing enabled. If using custom password field name, add protected $authPasswordName = \'custom_field_name\'; To disable: set rehash_on_login => false in config/hashing.php */
class User extends Authenticatable
{
    protected $custom_password_field;
}'
                ),
            ]
        );
    }
}