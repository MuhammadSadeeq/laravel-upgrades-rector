<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 12: the 'local' disk root default changed from storage_path('app')
 * to storage_path('app/private'). Flags config/filesystems.php arrays missing
 * an explicit 'local' root.
 *
 * @implements Rule<MethodCall>
 */
final class LocalDiskDefaultRootRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        // Only flag in config/filesystems.php files (matched via basename).
        $filePath = str_replace('\\', '/', $scope->getFile());

        if (! str_ends_with($filePath, 'filesystems.php')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'If no "local" disk is explicitly defined, Laravel now defaults it to storage_path("app/private").'
            )->identifier('laravelUpgrade.localDiskDefaultRoot')
                ->tip('Define disks.local.root explicitly to preserve storage/app behaviour.')
                ->build(),
        ];
    }
}
