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
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\Token;
use RuntimeException;
use Throwable;

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

        $projectDocument = $this->parseDocument($projectSource, basename($projectConfigPath));
        $upstreamAst = $this->parseReturnArray($upstreamSource, basename($upstreamConfigPath));
        $policies = $targetMajor > 0 ? $this->policies($targetMajor) : [];
        $changed = $this->mergeArray(
            $projectDocument['array'],
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

        return $this->printSource($projectSource, $projectDocument);
    }

    /**
     * Merge a config file while comparing it with the previous skeleton.
     * This enables truthful findings for upstream removals and changed
     * defaults without ever replacing an existing project value.
     */
    public function mergeWithBase(
        string $projectConfigPath,
        string $baseConfigPath,
        string $upstreamConfigPath,
        ?FindingCollector $collector = null,
        int $targetMajor = 0
    ): string {
        $projectSource = file_get_contents($projectConfigPath);
        $baseSource = file_get_contents($baseConfigPath);
        $upstreamSource = file_get_contents($upstreamConfigPath);

        if ($projectSource === false || $baseSource === false || $upstreamSource === false) {
            throw new RuntimeException(sprintf('Could not read config files for "%s".', basename($projectConfigPath)));
        }

        $projectDocument = $this->parseDocument($projectSource, basename($projectConfigPath));
        $baseAst = $this->parseReturnArray($baseSource, basename($baseConfigPath));
        $upstreamAst = $this->parseReturnArray($upstreamSource, basename($upstreamConfigPath));
        $policies = $targetMajor > 0 ? $this->policies($targetMajor) : [];

        $this->reportBaseDifferences(
            $projectDocument['array'],
            $baseAst,
            $upstreamAst,
            '',
            $policies,
            $collector,
            $targetMajor,
            basename($projectConfigPath),
        );

        $changed = $this->mergeArray(
            $projectDocument['array'],
            $upstreamAst,
            '',
            $policies,
            $collector,
            $targetMajor,
            basename($projectConfigPath),
        );

        if (! $changed) {
            return $projectSource;
        }

        return $this->printSource($projectSource, $projectDocument);
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
            $policy = $this->policyFor($policies, $path, $file);
            $projectItem = $projectIndexes[$key] ?? null;

            if ($projectItem === null) {
                $newItem = clone $upstreamItem;

                if ($policy !== null && array_key_exists('preserveValue', $policy)) {
                    $newItem->value = $this->policyExpression($policy['preserveValue']);

                    if ($collector !== null && $targetMajor > 0) {
                        $this->addPolicyFinding(
                            $collector,
                            $policy,
                            $targetMajor,
                            $file,
                            $path,
                            $upstreamItem->getStartLine(),
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

    /**
     * Report changes between the old and target skeleton while retaining the
     * project's value. The recursive walk deliberately only reports a removed
     * key when the project still has that key, avoiding noise for values that
     * were never customized or present in the application.
     *
     * @param  array<string, array<string, mixed>>  $policies
     */
    private function reportBaseDifferences(
        Array_ $project,
        Array_ $base,
        Array_ $upstream,
        string $prefix,
        array $policies,
        ?FindingCollector $collector,
        int $targetMajor,
        string $file,
    ): void {
        if ($collector === null || $targetMajor <= 0) {
            return;
        }

        $projectItems = $this->indexItems($project);
        $baseItems = $this->indexItems($base);
        $upstreamItems = $this->indexItems($upstream);

        foreach ($baseItems as $key => $baseItem) {
            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            $projectItem = $projectItems[$key] ?? null;
            $upstreamItem = $upstreamItems[$key] ?? null;

            if ($upstreamItem === null) {
                if ($projectItem !== null) {
                    $collector->add(
                        'laravelUpgrade.configKeyRemoved',
                        Finding::SEVERITY_MEDIUM,
                        $targetMajor,
                        'config/'.$file,
                        max(0, $projectItem->getStartLine()),
                        sprintf('Laravel %d removed config key "%s" from the skeleton.', $targetMajor, $path),
                        'Keep it if your application uses it; otherwise remove it after reviewing the upgrade guide.',
                    );
                }

                continue;
            }

            if ($projectItem !== null
                && (! $baseItem->value instanceof Array_ || ! $upstreamItem->value instanceof Array_)
                && ! $this->expressionsEqual($baseItem->value, $upstreamItem->value)
                && ! $this->expressionsEqual($projectItem->value, $upstreamItem->value)) {
                $policy = $this->policyFor($policies, $path, $file);

                if ($policy !== null) {
                    $this->addPolicyFinding(
                        $collector,
                        $policy,
                        $targetMajor,
                        $file,
                        $path,
                        $projectItem->getStartLine(),
                    );
                } else {
                    $collector->add(
                        'laravelUpgrade.configDefaultChanged',
                        Finding::SEVERITY_INFO,
                        $targetMajor,
                        'config/'.$file,
                        max(0, $projectItem->getStartLine()),
                        sprintf('Laravel %d changed the default value for config key "%s"; the project value was kept.', $targetMajor, $path),
                        'Review the new default and change this value deliberately if appropriate.',
                    );
                }
            } elseif ($projectItem !== null) {
                $policy = $this->policyFor($policies, $path, $file);

                if ($policy !== null && ($policy['informational'] ?? false) === true
                    && (! $this->expressionsEqual($baseItem->value, $upstreamItem->value)
                        || ($policy['transition'] ?? false) === true)) {
                    $this->addPolicyFinding(
                        $collector,
                        $policy,
                        $targetMajor,
                        $file,
                        $path,
                        $projectItem->getStartLine(),
                    );
                }
            }

            if ($projectItem?->value instanceof Array_
                && $baseItem->value instanceof Array_
                && $upstreamItem->value instanceof Array_) {
                $this->reportBaseDifferences(
                    $projectItem->value,
                    $baseItem->value,
                    $upstreamItem->value,
                    $path,
                    $policies,
                    $collector,
                    $targetMajor,
                    $file,
                );
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $policies
     * @return array<string, mixed>|null
     */
    private function policyFor(array $policies, string $path, string $file): ?array
    {
        $fileBase = pathinfo($file, PATHINFO_FILENAME);
        $normalizedFile = str_replace('/', '.', ltrim($file, './'));
        $candidates = [
            $normalizedFile.'.'.$path,
            $fileBase.'.'.$path,
        ];

        if (! str_starts_with($normalizedFile, 'config.')) {
            $candidates[] = 'config.'.$normalizedFile.'.'.$path;
        }

        $candidates = array_map(static fn (string $candidate): string => str_replace('/', '.', $candidate), $candidates);

        foreach ($policies as $key => $policy) {
            $normalized = str_replace('/', '.', $key);

            if (in_array($normalized, $candidates, true)) {
                return $policy;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $policy */
    private function addPolicyFinding(
        FindingCollector $collector,
        array $policy,
        int $targetMajor,
        string $file,
        string $path,
        int $line,
    ): void {
        $severity = $policy['severity'] ?? (($policy['informational'] ?? false) ? Finding::SEVERITY_INFO : Finding::SEVERITY_HIGH);
        $severity = is_string($severity) ? $severity : Finding::SEVERITY_MEDIUM;
        $message = $policy['message'] ?? $policy['reason'] ?? sprintf('Laravel %d changed config key "%s".', $targetMajor, $path);
        $action = $policy['action'] ?? sprintf('The project value for "%s" was kept. Review the new Laravel default before changing it.', $path);
        $guide = $policy['guide'] ?? $policy['guideUrl'] ?? '';

        $collector->add(
            'laravelUpgrade.configBehaviourChange',
            $severity,
            $targetMajor,
            'config/'.$file,
            max(0, $line),
            is_string($message) ? $message : sprintf('Laravel %d changed config key "%s".', $targetMajor, $path),
            is_string($action) ? $action : '',
            is_string($guide) ? $guide : '',
        );
    }

    private function expressionsEqual(Expr $left, Expr $right): bool
    {
        return $this->printer->prettyPrintExpr($left) === $this->printer->prettyPrintExpr($right);
    }

    private function parseReturnArray(string $source, string $name): Array_
    {
        return $this->findReturnArray($this->parseStatements($source, $name), $name);
    }

    /**
     * Parse a project config and clone its nodes with links to the original
     * tree. This is required by PhpParser's format-preserving printer: only
     * the inserted ArrayItems are reprinted while the original whitespace,
     * comments, and line endings remain byte-for-byte intact.
     *
     * @return array{array: Array_, original: list<Stmt>, working: list<Stmt>, tokens: list<Token>}
     */
    private function parseDocument(string $source, string $name): array
    {
        $original = $this->parseStatements($source, $name);
        $working = (new NodeTraverser(new CloningVisitor))->traverse($original);
        $working = array_values(array_filter(
            $working,
            static fn (mixed $statement): bool => $statement instanceof Stmt,
        ));

        return [
            'array' => $this->findReturnArray($working, $name),
            'original' => $original,
            'working' => $working,
            'tokens' => array_values($this->parser->getTokens()),
        ];
    }

    /**
     * @return list<Stmt>
     */
    private function parseStatements(string $source, string $name): array
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

        if ($ast === null) {
            throw new RuntimeException(sprintf('Cannot parse "%s".', $name));
        }

        return array_values($ast);
    }

    /**
     * @param  list<Stmt>  $statements
     */
    private function findReturnArray(array $statements, string $name): Array_
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Return_ && $statement->expr instanceof Array_) {
                return $statement->expr;
            }
        }

        throw new RuntimeException(sprintf('"%s" does not contain a return [...] statement.', $name));
    }

    /**
     * @param  array{array: Array_, original: list<Stmt>, working: list<Stmt>, tokens: list<Token>}  $document
     */
    private function printSource(string $source, array $document): string
    {
        try {
            return $this->printer->printFormatPreserving(
                $document['working'],
                $document['original'],
                $document['tokens'],
            );
        } catch (Throwable) {
            // Keep a safe fallback for parser versions that cannot format
            // preserve a particular PHP construct. This path is still
            // limited to the returned config expression.
            $returnStart = null;

            try {
                $statements = $this->parser->parse($source) ?? [];
            } catch (Error) {
                return "<?php\n\nreturn ".$this->printArray($document['array']).";\n";
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

                    return $prefix.'return '.$this->printArray($document['array']).$suffix;
                }
            }
        }

        return "<?php\n\nreturn ".$this->printArray($document['array']).";\n";
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
        $flatten = function (array $items, string $prefix = '') use (&$flatten, &$result): void {
            foreach ($items as $key => $policy) {
                if (! is_string($key) || ! is_array($policy)) {
                    continue;
                }

                $path = $prefix === '' ? $key : $prefix.'.'.$key;
                $isLeaf = array_intersect(
                    ['preserveValue', 'upstreamDefault', 'severity', 'informational', 'transition', 'message', 'reason', 'guide', 'guideUrl', 'action'],
                    array_keys($policy),
                ) !== [];

                if ($isLeaf) {
                    /** @var array<string, mixed> $policy */
                    $result[$path] = $policy;

                    continue;
                }

                $flatten($policy, $path);
            }
        };
        $flatten($section);

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
