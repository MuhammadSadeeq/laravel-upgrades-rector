<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;

/**
 * Answers "is this imported class actually referenced anywhere in the file?"
 * before an import may be removed.
 *
 * References are looked up in the AST (any name node equal to the alias or
 * the FQCN, including attribute/parameter/return positions) and, more
 * conservatively, in the raw file contents so docblock annotations such as
 * var/param/return/throws count too. When in doubt the answer is "used".
 */
final class ImportUsageChecker
{
    /**
     * @param array<Node\Stmt> $fileStmts
     */
    public function isUsed(array $fileStmts, string $rawFileContents, string $fqcn, string $localAlias): bool
    {
        // Rector's file container wraps the statement list.
        if ($fileStmts !== [] && $fileStmts[0] instanceof \Rector\PhpParser\Node\FileNode) {
            $fileStmts = $fileStmts[0]->stmts;
        }

        $referencedNames = [];
        $this->collectNames($fileStmts, $referencedNames);

        foreach ($referencedNames as $referenced) {
            if ($this->namesEqual($referenced, $localAlias) || $this->namesEqual($referenced, $fqcn)) {
                return true;
            }
        }

        // Conservative textual fallback catches docblocks and strings that
        // carry class references (@var Type, ::class in config values ...).
        return $this->appearsInText($rawFileContents, $localAlias)
            || $this->appearsInText($rawFileContents, $fqcn);
    }

    /**
     * Collects every non-import Name string in the file.
     *
     * @param array<Node\Stmt> $stmts
     * @param list<string> $out
     */
    private function collectNames(array $stmts, array &$out): void
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Use_) {
                continue; // imports themselves are not usages
            }

            foreach ($stmt->getSubNodeNames() as $subNodeName) {
                $value = $stmt->{$subNodeName};

                if ($value instanceof Node) {
                    $this->collectFromNode($value, $out);
                } elseif (is_array($value)) {
                    foreach ($value as $item) {
                        if ($item instanceof Node) {
                            $this->collectFromNode($item, $out);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param list<string> $out
     */
    private function collectFromNode(Node $node, array &$out): void
    {
        // Import statements are never usages, wherever they appear.
        if ($node instanceof Use_ || $node instanceof \PhpParser\Node\Stmt\GroupUse) {
            return;
        }

        if ($node instanceof Name) {
            $out[] = $node->toString();

            return;
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $value = $node->{$subNodeName};

            if ($value instanceof Node) {
                $this->collectFromNode($value, $out);
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof Node) {
                        $this->collectFromNode($item, $out);
                    }
                }
            }
        }
    }

    private function namesEqual(string $left, string $right): bool
    {
        return strcasecmp(ltrim($left, '\\'), ltrim($right, '\\')) === 0;
    }

    private function appearsInText(string $contents, string $name): bool
    {
        $shortName = substr($name, (int) strrpos($name, '\\') + 1);

        // The import statements themselves must not count as references.
        $contentsWithoutImports = (string) preg_replace('/^\s*use\s+[^;]+;\s*$/m', '', $contents);

        if ($contentsWithoutImports === '') {
            $contentsWithoutImports = $contents;
        }

        if (str_contains($contentsWithoutImports, $name)) {
            return true;
        }

        // Word-boundary, case-sensitive match on the short name catches
        // docblocks (@var Cache ...) without treating every once() helper
        // call as a reference to Spatie\Once.
        $pattern = '/\b' . preg_quote($shortName, '/') . '\b/';

        return preg_match($pattern, $contentsWithoutImports) === 1;
    }
}
