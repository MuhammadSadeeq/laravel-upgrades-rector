<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer;

use PhpParser\Comment;
use PhpParser\Node\Stmt\Nop;

/**
 * Creates Nop statements carrying TODO comments. Rules must dedupe by their
 * own marker text so repeated runs never stack duplicate comments.
 */
final class TodoNopFactory
{
    public static function create(string $message): Nop
    {
        $nop = new Nop;
        $nop->setAttribute('comments', [new Comment(sprintf('// TODO: %s', $message))]);

        return $nop;
    }

    public static function implementMessage(string $methodName, int $laravelMajor): string
    {
        return sprintf(
            'Laravel %d — implement %s() to satisfy the updated contract.',
            $laravelMajor,
            $methodName
        );
    }
}
