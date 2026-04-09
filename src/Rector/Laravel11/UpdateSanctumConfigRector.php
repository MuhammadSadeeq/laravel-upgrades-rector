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
    private const LEGACY_VERIFY_CSRF_TOKEN_KEY = 'verify_csrf_token';

    private const CURRENT_VERIFY_CSRF_TOKEN_KEY = 'validate_csrf_token';

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
        self::CURRENT_VERIFY_CSRF_TOKEN_KEY => [
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

        $existingMiddlewareKeys = $this->collectMiddlewareKeys($middlewareArray);
        $changed = false;
        $updatedItems = [];

        foreach ($middlewareArray->items as $item) {
            if (! $item instanceof ArrayItem) {
                $updatedItems[] = $item;
                continue;
            }

            if (! $item->key instanceof String_) {
                $updatedItems[] = $item;
                continue;
            }

            $key = $item->key->value;

            if (
                $key === self::LEGACY_VERIFY_CSRF_TOKEN_KEY
                && isset($existingMiddlewareKeys[self::CURRENT_VERIFY_CSRF_TOKEN_KEY])
            ) {
                $changed = true;
                continue;
            }

            if ($key === self::LEGACY_VERIFY_CSRF_TOKEN_KEY) {
                $item->key = new String_(self::CURRENT_VERIFY_CSRF_TOKEN_KEY);
                $key = self::CURRENT_VERIFY_CSRF_TOKEN_KEY;
                $changed = true;
            }

            if (! isset($this->middlewareMap[$key])) {
                $updatedItems[] = $item;
                continue;
            }

            // Skip if already a class reference
            if ($item->value instanceof ClassConstFetch) {
                $updatedItems[] = $item;
                continue;
            }

            // Only replace known old default string values
            if (! $item->value instanceof String_) {
                $updatedItems[] = $item;
                continue;
            }

            $mapping = $this->middlewareMap[$key];
            $currentValue = $item->value->value;

            // Only replace if it matches a known old default value
            if (! in_array($currentValue, $mapping['old_defaults'], true)) {
                $updatedItems[] = $item;
                continue;
            }

            $item->value = new ClassConstFetch(new FullyQualified($mapping['fqcn']), 'class');
            $changed = true;
            $updatedItems[] = $item;
        }

        if (! $changed) {
            return null;
        }

        $middlewareArray->items = $updatedItems;

        return $node;
    }

    /**
     * @return array<string, true>
     */
    private function collectMiddlewareKeys(Array_ $array): array
    {
        $keys = [];

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            $keys[$item->key->value] = true;
        }

        return $keys;
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
