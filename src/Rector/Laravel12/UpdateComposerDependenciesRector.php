<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use MuhammadSadeeq\LaravelUpgradesRector\Support\Composer\ComposerJsonPathResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Support\Composer\Laravel12ComposerJsonUpdater;
use PhpParser\Node;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateComposerDependenciesRector extends AbstractRector
{
    /** @var array<string, true> */
    private array $updatedComposerJsonPaths = [];

    public function __construct(
        private readonly ComposerJsonPathResolver $composerJsonPathResolver,
        private readonly Laravel12ComposerJsonUpdater $laravel12ComposerJsonUpdater,
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
        $this->laravel12ComposerJsonUpdater->update($composerJsonPath);

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update composer.json dependencies for Laravel 12 compatibility',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
{
    "require": {
        "laravel/framework": "^11.31"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5",
        "pestphp/pest": "^2.0"
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
{
    "require": {
        "laravel/framework": "^12.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "pestphp/pest": "^3.0"
    }
}
CODE_SAMPLE,
                ),
            ]
        );
    }
}
