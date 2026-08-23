<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer;

use PhpParser\Comment;
use PhpParser\Node;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * Attaches deduped advisory comments to nodes.
 *
 * Under Rector 2.x there are no parent-node attributes, so instead of
 * inserting Nop sibling statements a comment is attached directly to the
 * smallest node carrying the finding — which keeps formatting intact as long
 * as ORIGINAL_NODE is never nulled (that attribute forces a full-class
 * reprint that deletes blank lines and collapses promoted constructors).
 *
 * Every rule passes its own marker constant so identical-looking notes from
 * different rules never silence each other.
 */
final class CommentInserter
{
    /**
     * @return bool true when a comment was added, false when the marker was already present
     */
    public function addComment(Node $node, string $marker, string $message): bool
    {
        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), $marker)) {
                return false;
            }
        }

        $comments = $node->getComments();
        $comments[] = new Comment(sprintf('// %s %s', $marker, $message));
        $node->setAttribute(AttributeKey::COMMENTS, $comments);

        return true;
    }
}
