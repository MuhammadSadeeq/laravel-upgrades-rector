<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Shared;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\TodoNopFactory;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Appends contract methods that a Laravel major added to framework
 * interfaces, configured through resources/contracts/laravel-<major>.php.
 *
 * Guarantees (decision D4 + D7):
 * - signatures are emitted verbatim from the verified data file — parameter
 *   types match the interface exactly, so the generated code loads;
 * - existing methods are never rewritten: only missing methods are appended;
 * - trait-provided and parent-provided methods count as present;
 * - abstract classes are left alone.
 *
 * Eloquent-aware specs may carry an alternative body for model subclasses
 * (e.g. MustVerifyEmail::markEmailAsUnverified using forceFill()).
 */
final class ImplementMissingInterfaceMethodsRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var list<ContractMethodSpec>
     */
    private array $specs = [];

    private int $major = 0;

    public function __construct(
        private readonly InterfaceImplementationChecker $interfaceImplementationChecker,
    ) {}

    /**
     * @param  mixed[]  $configuration  list of ContractMethodSpec instances
     */
    public function configure(array $configuration): void
    {
        $major = 0;

        foreach ($configuration as $item) {
            if (is_int($item)) {
                $major = $item;

                continue;
            }

            if (! $item instanceof ContractMethodSpec) {
                throw new \InvalidArgumentException(sprintf(
                    'configure() expects ContractMethodSpec instances and one integer major, got %s.',
                    get_debug_type($item)
                ));
            }
        }

        if ($major < 8 || $major > 20) {
            throw new \InvalidArgumentException('configure() needs the Laravel major as an integer entry.');
        }

        $this->major = $major;
        $this->specs = array_values(array_filter(
            $configuration,
            static fn ($item): bool => $item instanceof ContractMethodSpec
        ));
    }

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_ || $node->isAbstract() || $node->isAnonymous()) {
            return null;
        }

        $changed = false;

        foreach ($this->specs as $spec) {
            if (! $this->interfaceImplementationChecker->implementsInterface($node, $spec->interface)) {
                continue;
            }

            if ($this->interfaceImplementationChecker->hasMethod($node, $spec->method)) {
                continue;
            }

            $node->stmts[] = $this->buildMissingMethod($node, $spec);
            $changed = true;
        }

        return $changed ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Append contract methods that the target Laravel major added to framework interfaces',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class CustomStore implements Illuminate\Contracts\Cache\Store
{
    public function get($key)
    {
    }
    // ...
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class CustomStore implements Illuminate\Contracts\Cache\Store
{
    public function get($key)
    {
    }
    // ...

    public function touch($key, $seconds)
    {
        // TODO: Laravel 13 — implement touch() to satisfy the updated contract.
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }

    private function buildMissingMethod(Class_ $class, ContractMethodSpec $spec): ClassMethod
    {
        $eloquent = $this->isEloquentModel($class);

        $definition = $eloquent && $spec->definitionEloquent !== null
            ? $spec->definitionEloquent
            : $spec->definition;

        $todo = $eloquent && $spec->todoEloquent !== null
            ? $spec->todoEloquent
            : $spec->todo;

        $classMethod = $this->parseMethod($definition);
        $classMethod->name = new Identifier($spec->method);

        $todoNop = TodoNopFactory::create(sprintf('Laravel %d — %s', $this->major, $todo));

        $classMethod->stmts = array_merge([$todoNop], $classMethod->stmts ?? []);

        return $classMethod;
    }

    /**
     * Parses the verbatim definition inside a temporary wrapper class and
     * returns the method node with fully-qualified type names.
     */
    private function parseMethod(string $definition): ClassMethod
    {
        $stub = "<?php\n\nnamespace LaravelUpgradesRector\\ContractSpec;\n\nclass __Spec__\n{\n    "
            .$definition."\n}\n";

        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($stub);
        } catch (Error $error) {
            throw new \InvalidArgumentException(sprintf(
                'Contract definition does not parse: %s',
                $error->getMessage()
            ));
        }

        $class = $ast[0] ?? null;

        if ($class instanceof Namespace_) {
            foreach ($class->stmts as $stmt) {
                if ($stmt instanceof Class_) {
                    $class = $stmt;

                    break;
                }
            }
        }

        if (! $class instanceof Class_) {
            throw new \InvalidArgumentException('Contract definition wrapper did not produce a class.');
        }

        $methods = $class->getMethods();

        $method = $methods[0] ?? throw new \InvalidArgumentException(
            'Contract definition produced no method.'
        );

        // The stub namespace differs from any user file; make every name
        // absolute so generated code resolves identically everywhere.
        $this->makeNamesFullyQualified($method);

        return $method;
    }

    private function makeNamesFullyQualified(Node $node): void
    {
        // Recurse over every sub-node; relative Name nodes in types/attributes
        // would otherwise resolve against the user's file namespace.
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $value = $node->{$subNodeName};

            if ($value instanceof Node\Name && ! $value->isFullyQualified()
                && ! $this->isBuiltinName($value->toString())) {
                $node->{$subNodeName} = new Node\Name\FullyQualified($value->toString());
            } elseif ($value instanceof Node) {
                $this->makeNamesFullyQualified($value);
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof Node) {
                        $this->makeNamesFullyQualified($item);
                    }
                }
            }
        }
    }

    private function isBuiltinName(string $name): bool
    {
        return in_array(strtolower($name), [
            'int', 'float', 'string', 'bool', 'array', 'mixed', 'void', 'null',
            'callable', 'iterable', 'object', 'false', 'true', 'static', 'self',
            'parent',
        ], true);
    }

    private function isEloquentModel(Class_ $class): bool
    {
        $scope = $class->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return false;
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection) {
            return false;
        }

        return $classReflection->is('Illuminate\Database\Eloquent\Model')
            || $classReflection->isSubclassOf('Illuminate\Database\Eloquent\Model');
    }
}
