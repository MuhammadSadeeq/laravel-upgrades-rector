<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\Composer\ComposerJsonPathResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Support\Composer\Laravel13ComposerJsonUpdater;
use PhpParser\Node;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateComposerDependenciesLaravel13Rector extends AbstractRector
{
    /** @var array<string, true> */
    private array $updatedComposerJsonPaths = [];

    public function __construct(
        private readonly ComposerJsonPathResolver $composerJsonPathResolver,
        private readonly Laravel13ComposerJsonUpdater $laravel13ComposerJsonUpdater,
    ) {
    }

    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof FileNode) {
            return null;
        }

        if (str_contains($this->file->getFilePath(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
            return null;
        }

        $composerJsonPath = $this->composerJsonPathResolver->resolveFromFilePath($this->file->getFilePath());

        if ($composerJsonPath === null || isset($this->updatedComposerJsonPaths[$composerJsonPath])) {
            return null;
        }

        $this->updatedComposerJsonPaths[$composerJsonPath] = true;
        $this->laravel13ComposerJsonUpdater->update($composerJsonPath);

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update dependency version strings for Laravel 13 compatibility',
            [
                new CodeSample(
                    '"laravel/framework" => "^12.0"',
                    '"laravel/framework" => "^13.0"',
                ),
            ]
        );
    }
}
