<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use PhpParser\Error;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;

/**
 * Merges configuration arrays between framework versions (plan P5-03).
 *
 * Adds keys present in the upstream config but absent locally, preserving
 * upstream defaults. Never removes local keys — those become findings.
 * Prints with Rector's printer so formatting of untouched parts survives.
 */
final class ConfigArrayMerger
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    /**
     * Merges upstream config array content into the project's config file,
     * adding only missing top-level keys. Returns the merged PHP source.
     *
     * @throws RuntimeException when either file cannot be parsed as a config array
     */
    public function merge(string $projectConfigPath, string $upstreamConfigPath): string
    {
        $projectSource = file_get_contents($projectConfigPath);

        if ($projectSource === false) {
            throw new RuntimeException(sprintf('Could not read "%s".', $projectConfigPath));
        }

        $upstreamSource = file_get_contents($upstreamConfigPath);

        if ($upstreamSource === false) {
            throw new RuntimeException(sprintf('Could not read "%s".', $upstreamConfigPath));
        }

        $projectAst = $this->parseReturnArray($projectSource, basename($projectConfigPath));
        $upstreamAst = $this->parseReturnArray($upstreamSource, basename($upstreamConfigPath));

        $projectKeys = $this->extractStringKeys($projectAst);
        $missingItems = [];

        foreach ($upstreamAst->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            $key = $this->extractKey($item);

            if ($key === null || in_array($key, $projectKeys, true)) {
                continue;
            }

            // Deep-copy the item so it's independent of the upstream tree.
            $missingItems[] = [
                'key' => $key,
                'serialized' => serialize($item),
            ];
        }

        if ($missingItems === []) {
            return $projectSource; // nothing to merge
        }

        foreach ($missingItems as $entry) {
            /** @var ArrayItem $restored */
            $restored = unserialize($entry['serialized'], ['allowed_classes' => true]);
            $projectAst->items[] = $restored;
        }

        return "<?php\n\nreturn ".$this->printArray($projectAst).";\n";
    }

    /**
     * Returns list of missing top-level string keys in the project config.
     *
     * @return list<string>
     */
    public function findMissingKeys(string $projectConfigPath, string $upstreamConfigPath): array
    {
        $projectSource = file_get_contents($projectConfigPath);
        $upstreamSource = file_get_contents($upstreamConfigPath);

        if ($projectSource === false || $upstreamSource === false) {
            return [];
        }

        try {
            $projectAst = $this->parseReturnArray($projectSource, basename($projectConfigPath));
            $upstreamAst = $this->parseReturnArray($upstreamSource, basename($upstreamConfigPath));
        } catch (RuntimeException) {
            return [];
        }

        $projectKeys = $this->extractStringKeys($projectAst);
        $missing = [];

        foreach ($upstreamAst->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            $key = $this->extractKey($item);

            if ($key !== null && ! in_array($key, $projectKeys, true)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private function parseReturnArray(string $source, string $name): Array_
    {
        try {
            $ast = $this->parser->parse($source);
        } catch (Error $error) {
            throw new RuntimeException(sprintf(
                'Cannot parse "%s": %s',
                $name,
                $error->getMessage()
            ));
        }

        foreach ($ast ?? [] as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Array_) {
                return $stmt->expr;
            }
        }

        throw new RuntimeException(sprintf('"%s" does not contain a return [...] statement.', $name));
    }

    /**
     * @return list<string|null>
     */
    private function extractStringKeys(Array_ $array): array
    {
        $keys = [];

        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem && $item->key instanceof String_) {
                $keys[] = $item->key->value;
            } else {
                $keys[] = null;
            }
        }

        return $keys;
    }

    private function extractKey(?ArrayItem $item): ?string
    {
        if ($item === null || ! $item->key instanceof String_) {
            return null;
        }

        return $item->key->value;
    }

    public function printArray(Array_ $array): string
    {
        $prettyPrinter = new Standard;

        return $prettyPrinter->prettyPrintExpr($array);
    }
}
