<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;

final class StaticCallExtractor
{
    public function extract(Expression $node): ?StaticCall
    {
        if ($node->expr instanceof StaticCall) {
            return $node->expr;
        }

        if (
            $node->expr instanceof Assign &&
            $node->expr->expr instanceof StaticCall
        ) {
            return $node->expr->expr;
        }

        return null;
    }
}
