<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use PhpParser\Error;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;

/**
 * Merges Laravel configuration arrays without deleting project values.
 *
 * Missing keys are copied recursively from the target skeleton. Existing
 * values always win; behaviour-changing keys can opt into a policy value and
 * produce a high-severity finding at the same time.
 */
final class ConfigArrayMerger
{
    private Parser $parser;

    private Standard $printer;

    private string $policyDirectory;

    public function __construct(?string $policyDirectory = null)
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->printer = new Standard;
        $this->policyDirectory = rtrim(
            $policyDirectory ?? dirname(__DIR__, 3).'/resources/config-policies',
            '/'
        );
    }

    /**
     * @throws RuntimeException when either file cannot be parsed as a config array
     */
    public function merge(
        string $projectConfigPath,
        string $upstreamConfigPath,
        ?FindingCollector $collector = null,
        int $targetMajor = 0
    ): string {
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
        $policies = $targetMajor > 0 ? $this->policies($targetMajor) : [];
        $changed = $this->mergeArray(
            $projectAst,
            $upstreamAst,
            '',
            $policies,
            $collector,
            $targetMajor,
            basename($projectConfigPath)
        );

        if (! $changed) {
            return $projectSource;
        }

        return $this->printSource($projectSource, $projectAst);
    }

    /**
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

        return $this->missingKeys($projectAst, $upstreamAst, '');
    }

    /**
     * Finds keys present in the old skeleton and absent in the target. This
     * lets SkeletonStep distinguish an upstream removal from a custom key.
     *
     * @return list<string>
     */
    public function findRemovedKeys(
        string $projectConfigPath,
        string $fromConfigPath,
        string $toConfigPath
    ): array {
        $projectSource = file_get_contents($projectConfigPath);
        $fromSource = file_get_contents($fromConfigPath);
        $toSource = file_get_contents($toConfigPath);

        if ($projectSource === false || $fromSource === false || $toSource === false) {
            return [];
        }

        try {
            $project = $this->parseReturnArray($projectSource, basename($projectConfigPath));
            $from = $this->parseReturnArray($fromSource, basename($fromConfigPath));
            $to = $this->parseReturnArray($toSource, basename($toConfigPath));
        } catch (RuntimeException) {
            return [];
        }

        $projectKeys = $this->keys($project);
        $fromKeys = $this->keys($from);
        $toKeys = $this->keys($to);
        $removed = [];

        foreach (array_intersect($projectKeys, $fromKeys) as $key) {
            if (! in_array($key, $toKeys, true)) {
                $removed[] = $key;
            }
        }

        return $removed;
    }

    public function printArray(Array_ $array): string
    {
        return $this->printer->prettyPrintExpr($array);
    }

    /**
     * @param  array<string, array<string, mixed>>  $policies
     */
    private function mergeArray(
        Array_ $project,
        Array_ $upstream,
        string $prefix,
        array $policies,
        ?FindingCollector $collector,
        int $targetMajor,
        string $file
    ): bool {
        $changed = false;
        $projectIndexes = $this->indexItems($project);

        foreach ($upstream->items as $upstreamItem) {
            if (! $upstreamItem instanceof ArrayItem || ! $upstreamItem->key instanceof String_) {
                continue;
            }

            $key = $upstreamItem->key->value;
            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            $policy = $policies[$path] ?? null;
            $projectItem = $projectIndexes[$key] ?? null;

            if ($projectItem === null) {
                $newItem = clone $upstreamItem;

                if ($policy !== null && array_key_exists('preserveValue', $policy)) {
                    $newItem->value = $this->policyExpression($policy['preserveValue']);

                    if ($collector !== null && $targetMajor > 0) {
                        $collector->add(
                            'laravelUpgrade.configBehaviourChange',
                            Finding::SEVERITY_HIGH,
                            $targetMajor,
                            'config/'.$file,
                            $upstreamItem->getStartLine(),
                            sprintf(
                                'New Laravel %d config key "%s" changes framework behaviour.',
                                $targetMajor,
                                $path
                            ),
                            sprintf(
                                'The previous-behaviour value was preserved. Review "%s" and migrate deliberately.',
                                $path
                            )
                        );
                    }
                }

                $this->insertAtUpstreamPosition($project, $upstream, $upstreamItem, $newItem);
                $projectIndexes = $this->indexItems($project);
                $changed = true;

                continue;
            }

            if ($projectItem->value instanceof Array_ && $upstreamItem->value instanceof Array_) {
                $changed = $this->mergeArray(
                    $projectItem->value,
                    $upstreamItem->value,
                    $path,
                    $policies,
                    $collector,
                    $targetMajor,
                    $file
                ) || $changed;
            }
        }

        return $changed;
    }

    /**
     * Inserts after the nearest existing upstream sibling, or before the next
     * existing sibling. This retains the target skeleton's relative order
     * while leaving custom project keys where they were.
     */
    private function insertAtUpstreamPosition(
        Array_ $project,
        Array_ $upstream,
        ArrayItem $upstreamItem,
        ArrayItem $newItem
    ): void {
        $upstreamKeys = [];

        foreach ($upstream->items as $item) {
            if ($item instanceof ArrayItem && $item->key instanceof String_) {
                $upstreamKeys[] = $item->key->value;
            }
        }

        $newKey = $upstreamItem->key instanceof String_ ? $upstreamItem->key->value : '';
        $newPosition = array_search($newKey, $upstreamKeys, true);

        if ($newPosition === false) {
            $project->items[] = $newItem;

            return;
        }

        for ($index = $newPosition - 1; $index >= 0; $index--) {
            $previous = $upstreamKeys[$index];

            foreach ($project->items as $projectIndex => $item) {
                if ($item instanceof ArrayItem && $item->key instanceof String_
                    && $item->key->value === $previous) {
                    array_splice($project->items, $projectIndex + 1, 0, [$newItem]);

                    return;
                }
            }
        }

        for ($index = $newPosition + 1, $count = count($upstreamKeys); $index < $count; $index++) {
            $next = $upstreamKeys[$index];

            foreach ($project->items as $projectIndex => $item) {
                if ($item instanceof ArrayItem && $item->key instanceof String_
                    && $item->key->value === $next) {
                    array_splice($project->items, $projectIndex, 0, [$newItem]);

                    return;
                }
            }
        }

        $project->items[] = $newItem;
    }

    /**
     * @return array<string, ArrayItem>
     */
    private function indexItems(Array_ $array): array
    {
        $indexed = [];

        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem && $item->key instanceof String_) {
                $indexed[$item->key->value] = $item;
            }
        }

        return $indexed;
    }

    /**
     * @return list<string>
     */
    private function missingKeys(Array_ $project, Array_ $upstream, string $prefix): array
    {
        $missing = [];
        $projectItems = $this->indexItems($project);

        foreach ($upstream->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            $key = $item->key->value;
            $path = $prefix === '' ? $key : $prefix.'.'.$key;

            if (! isset($projectItems[$key])) {
                $missing[] = $path;

                continue;
            }

            $projectValue = $projectItems[$key]->value;

            if ($projectValue instanceof Array_ && $item->value instanceof Array_) {
                array_push($missing, ...$this->missingKeys($projectValue, $item->value, $path));
            }
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    private function keys(Array_ $array): array
    {
        $keys = [];

        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem && $item->key instanceof String_) {
                $keys[] = $item->key->value;
            }
        }

        return $keys;
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

    private function printSource(string $source, Array_ $array): string
    {
        $returnStart = null;

        try {
            $statements = $this->parser->parse($source) ?? [];
        } catch (Error) {
            return "<?php\n\nreturn ".$this->printArray($array).";\n";
        }

        foreach ($statements as $statement) {
            if ($statement instanceof Return_ && $statement->expr instanceof Array_) {
                $returnStart = $statement->getStartFilePos();
                $expressionEnd = $statement->expr->getEndFilePos();
                $suffix = $expressionEnd >= 0 ? substr($source, $expressionEnd + 1) : ";\n";

                if ($suffix === '') {
                    $suffix = ";\n";
                }

                $prefix = $returnStart >= 0 ? substr($source, 0, $returnStart) : "<?php\n\n";

                return $prefix.'return '.$this->printArray($array).$suffix;
            }
        }

        return "<?php\n\nreturn ".$this->printArray($array).";\n";
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function policies(int $targetMajor): array
    {
        $path = $this->policyDirectory.'/'.$targetMajor.'.json';

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        $section = $decoded['behaviourChanging'] ?? $decoded['behaviorChanging'] ?? $decoded;

        if (! is_array($section)) {
            return [];
        }

        $result = [];

        foreach ($section as $key => $policy) {
            if (is_string($key) && is_array($policy)) {
                /** @var array<string, mixed> $policy */
                $result[$key] = $policy;
            }
        }

        return $result;
    }

    private function policyExpression(mixed $value): Expr
    {
        if ($value === null) {
            return new ConstFetch(new Name('null'));
        }

        if (is_bool($value)) {
            return new ConstFetch(new Name($value ? 'true' : 'false'));
        }

        if (is_int($value)) {
            return new LNumber($value);
        }

        if (is_float($value)) {
            return new DNumber($value);
        }

        if (is_string($value)) {
            // A policy may opt into a PHP expression (for example env(...));
            // plain strings remain safe quoted scalar values.
            if (preg_match('/^(?:env|config|storage_path|base_path)\s*\(/', $value) === 1) {
                try {
                    $ast = $this->parser->parse("<?php return {$value};");

                    if (($ast[0] ?? null) instanceof Return_ && $ast[0]->expr instanceof Expr) {
                        return $ast[0]->expr;
                    }
                } catch (Error) {
                    // Fall through to a quoted value for malformed policy data.
                }
            }

            return new String_($value);
        }

        $serialized = json_encode($value);

        return new String_(is_string($serialized) ? $serialized : '');
    }
}
