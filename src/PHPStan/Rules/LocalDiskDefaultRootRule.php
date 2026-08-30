<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Laravel 12: the local disk root defaults to storage_path('app/private')
 * when no explicit local.root is configured. The project context is supplied
 * by the generated PHPStan config; this rule only reports actual default/local
 * disk usage in that case. A null context is intentionally a safe no-op so the
 * packaged rule configuration can be loaded on its own.
 *
 * @implements Rule<Node>
 */
final class LocalDiskDefaultRootRule implements Rule
{
    /** @var list<string> */
    private const DEFAULT_DISK_METHODS = [
        'append', 'copy', 'delete', 'deletedirectory', 'directories', 'download',
        'exists', 'fileexists', 'files', 'get', 'getdriver', 'getvisibility',
        'lastmodified', 'makedirectory', 'mimetype', 'missing', 'move', 'path',
        'prepend', 'put', 'putfile', 'putfileas', 'readstream', 'size',
        'temporaryurl', 'url', 'writestream',
    ];

    public function __construct(
        private readonly ?bool $localDiskRootConfigured = null,
        private readonly ?bool $localDiskIsDefault = null,
    ) {}

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->localDiskRootConfigured !== false || $this->localDiskIsDefault === null) {
            return [];
        }

        if ($node instanceof StaticCall && $this->isStorageFacade($node, $scope)) {
            if ($this->isLocalDiskCall($node) || $this->isDefaultDiskCall($node)) {
                return [$this->error()];
            }

            if ($node->name instanceof Identifier
                && $this->localDiskIsDefault
                && in_array($node->name->toLowerString(), self::DEFAULT_DISK_METHODS, true)) {
                return [$this->error()];
            }
        }

        if ($node instanceof MethodCall
            && $this->isFilesystemManager($node, $scope)
            && ($this->isLocalDiskCall($node) || $this->isDefaultDiskCall($node))) {
            return [$this->error()];
        }

        return [];
    }

    private function isStorageFacade(StaticCall $call, Scope $scope): bool
    {
        return $call->class instanceof Name
            && in_array(ltrim($scope->resolveName($call->class), '\\'), [
                'Illuminate\Support\Facades\Storage',
                'Storage',
            ], true);
    }

    private function isFilesystemManager(MethodCall $call, Scope $scope): bool
    {
        return (new ObjectType('Illuminate\Filesystem\FilesystemManager'))
            ->isSuperTypeOf($scope->getType($call->var))->yes();
    }

    private function isLocalDiskCall(StaticCall|MethodCall $call): bool
    {
        if (! $call->name instanceof Identifier || $call->name->toLowerString() !== 'disk') {
            return false;
        }

        $argument = $call->args[0] ?? null;

        return $argument instanceof Node\Arg
            && $argument->value instanceof String_
            && strtolower($argument->value->value) === 'local';
    }

    private function isDefaultDiskCall(StaticCall|MethodCall $call): bool
    {
        return $this->localDiskIsDefault === true
            && $call->name instanceof Identifier
            && $call->name->toLowerString() === 'disk'
            && $call->args === [];
    }

    private function error(): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'If no "local" disk is explicitly defined, Laravel now defaults it to storage_path("app/private").'
        )->identifier('laravelUpgrade.localDiskDefaultRoot')
            ->tip('Define disks.local.root explicitly to preserve storage/app behaviour.')
            ->build();
    }
}
