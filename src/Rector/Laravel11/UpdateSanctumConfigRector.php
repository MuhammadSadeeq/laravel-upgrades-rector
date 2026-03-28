<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSanctumConfigRector extends AbstractRector
{
    /** @var array<string, array{fqcn: string, old_defaults: array<int, string>}> */
    private array $middlewareMap = [
        'authenticate_session' => [
            'fqcn' => 'Laravel\\Sanctum\\Http\\Middleware\\AuthenticateSession',
            'old_defaults' => [
                'App\\Http\\Middleware\\AuthenticateSession',
                'Laravel\\Sanctum\\Http\\Middleware\\AuthenticateSession',
            ],
        ],
        'encrypt_cookies' => [
            'fqcn' => 'Illuminate\\Cookie\\Middleware\\EncryptCookies',
            'old_defaults' => [
                'App\\Http\\Middleware\\EncryptCookies',
                'Illuminate\\Cookie\\Middleware\\EncryptCookies',
            ],
        ],
        'validate_csrf_token' => [
            'fqcn' => 'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
            'old_defaults' => [
                'App\\Http\\Middleware\\VerifyCsrfToken',
                'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
            ],
        ],
    ];

    public function getNodeTypes(): array
    {
        return [Array_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Array_) {
            return null;
        }

        if (! $this->isSanctumMiddlewareArray($node)) {
            return null;
        }

        $middlewareArray = $this->findMiddlewareArray($node);

        if (! $middlewareArray instanceof Array_) {
            return null;
        }

        $changed = false;

        foreach ($middlewareArray->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if (! $item->key instanceof String_) {
                continue;
            }

            $key = $item->key->value;

            if (! isset($this->middlewareMap[$key])) {
                continue;
            }

            // Skip if already a class reference
            if ($item->value instanceof ClassConstFetch) {
                continue;
            }

            // Only replace known old default string values
            if (! $item->value instanceof String_) {
                continue;
            }

            $mapping = $this->middlewareMap[$key];
            $currentValue = $item->value->value;

            // Only replace if it matches a known old default value
            if (! in_array($currentValue, $mapping['old_defaults'], true)) {
                continue;
            }

            $item->value = new ClassConstFetch(new FullyQualified($mapping['fqcn']), 'class');
            $changed = true;
        }

        if (! $changed) {
            return null;
        }

        return $node;
    }

    private function isSanctumMiddlewareArray(Array_ $array): bool
    {
        $hasMiddleware = false;
        $hasSanctumKey = false;

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value === 'middleware') {
                $hasMiddleware = true;
            }

            if (in_array($item->key->value, ['stateful', 'guard', 'expiration'], true)) {
                $hasSanctumKey = true;
            }
        }

        return $hasMiddleware && $hasSanctumKey;
    }

    private function findMiddlewareArray(Array_ $array): ?Array_
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if (! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value === 'middleware' && $item->value instanceof Array_) {
                return $item->value;
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update Sanctum middleware configuration to use class references for Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
return [
    'stateful' => [],
    'middleware' => [
        'authenticate_session' => 'App\Http\Middleware\AuthenticateSession',
    ],
];
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
return [
    'stateful' => [],
    'middleware' => [
        'authenticate_session' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
    ],
];
CODE_SAMPLE
                ),
            ]
        );
    }
}
