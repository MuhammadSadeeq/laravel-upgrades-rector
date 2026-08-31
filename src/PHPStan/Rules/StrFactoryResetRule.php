<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 13: Str factory instances are reset between tests by the framework.
 * Code mutating Str's UUID, ULID, or random-string factories inside test
 * methods should be aware that factories are now reset per-test.
 *
 * @implements Rule<StaticCall>
 */
final class StrFactoryResetRule implements Rule
{
    /** @var list<string> */
    private const FACTORY_METHODS = [
        'createrandomstringsusing',
        'createrandomstringsusingsequence',
        'createrandomstringsnormally',
        'createuuidsusing',
        'createuuidsusingsequence',
        'createuuidsnormally',
        'createulidsusing',
        'createulidsusingsequence',
        'createulidsnormally',
        'freezeuuids',
        'freezeulids',
    ];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $file = str_replace('\\', '/', $scope->getFile());

        if (preg_match('~(?:^|/)tests(?:/|$)~', $file) !== 1) {
            return [];
        }

        if (! $node->name instanceof Identifier
            || ! in_array($node->name->toLowerString(), self::FACTORY_METHODS, true)) {
            return [];
        }

        if (! $node->class instanceof Name) {
            return [];
        }

        $raw = ltrim($scope->resolveName($node->class), '\\');
        if ($raw !== 'Illuminate\Support\Str' && $raw !== 'Str') {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Custom Str factories are reset between tests in Laravel 13.'
            )->identifier('laravelUpgrade.strFactoryReset')
                ->tip('Register UUID, ULID, and random-string factories in each relevant test or setup hook.')
                ->build(),
        ];
    }
}
