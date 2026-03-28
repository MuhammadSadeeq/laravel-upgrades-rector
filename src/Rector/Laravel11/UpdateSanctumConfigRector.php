<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSanctumConfigRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Array_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Array_) {
            return null;
        }

        $hasMiddlewareKey = false;
        $middlewareUpdated = false;

        foreach ($node->items as $item) {
            if (!$item instanceof ArrayItem) {
                continue;
            }

            if (!$item->key instanceof String_) {
                continue;
            }

            // Check if this is the middleware configuration array
            if (
                $item->key->value === "middleware" &&
                $item->value instanceof Array_
            ) {
                $hasMiddlewareKey = true;
                $middlewareArray = $item->value;

                foreach ($middlewareArray->items as $middlewareItem) {
                    if (!$middlewareItem instanceof ArrayItem) {
                        continue;
                    }

                    if (!$middlewareItem->key instanceof String_) {
                        continue;
                    }

                    $key = $middlewareItem->key->value;
                    $updated = false;

                    // Update middleware class references
                    if (
                        $key === "authenticate_session" &&
                        $middlewareItem->value instanceof String_
                    ) {
                        if (
                            $middlewareItem->value->value !==
                            "Laravel\Sanctum\Http\Middleware\AuthenticateSession::class"
                        ) {
                            $middlewareItem->value = new String_(
                                "Laravel\Sanctum\Http\Middleware\AuthenticateSession::class",
                            );
                            $updated = true;
                        }
                    }

                    if (
                        $key === "encrypt_cookies" &&
                        $middlewareItem->value instanceof String_
                    ) {
                        if (
                            $middlewareItem->value->value !==
                            "Illuminate\Cookie\Middleware\EncryptCookies::class"
                        ) {
                            $middlewareItem->value = new String_(
                                "Illuminate\Cookie\Middleware\EncryptCookies::class",
                            );
                            $updated = true;
                        }
                    }

                    if (
                        $key === "validate_csrf_token" &&
                        $middlewareItem->value instanceof String_
                    ) {
                        if (
                            $middlewareItem->value->value !==
                            "Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class"
                        ) {
                            $middlewareItem->value = new String_(
                                "Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class",
                            );
                            $updated = true;
                        }
                    }

                    if ($updated) {
                        $middlewareUpdated = true;
                    }
                }
            }
        }

        if ($hasMiddlewareKey && $middlewareUpdated) {
            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Update Sanctum middleware configuration for Laravel 11",
            [
                new CodeSample(
                    "'middleware' => [
    'authenticate_session' => 'old_middleware_reference',
    'encrypt_cookies' => 'old_middleware_reference',
    'validate_csrf_token' => 'old_middleware_reference',
]",
                    "'middleware' => [
    'authenticate_session' => 'Laravel\Sanctum\Http\Middleware\AuthenticateSession::class',
    'encrypt_cookies' => 'Illuminate\Cookie\Middleware\EncryptCookies::class',
    'validate_csrf_token' => 'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class',
]",
                ),
            ],
        );
    }
}
