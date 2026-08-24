<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Support;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\ArgumentHelper;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\StatementCallFinder;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class NodeAnalyzersTest extends TestCase
{
    private StatementCallFinder $callFinder;

    private ArgumentHelper $argumentHelper;

    private Parser $parser;

    protected function setUp(): void
    {
        $this->callFinder = new StatementCallFinder;
        $this->argumentHelper = new ArgumentHelper;
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    public function test_statement_call_finder_finds_calls_in_every_shape(): void
    {
        $statements = $this->parse(<<<'PHP'
<?php

$result = $service->handle($input);
return $factory->make('table');
Schema::getAllTables();
$items = ['first' => $a->first(), 'second' => Static::second()];
someCall($service->nested($x));
new NewInstance($dep);
PHP);

        $calls = [];

        foreach ($statements as $statement) {
            foreach ($this->callFinder->find($statement) as $call) {
                $calls[] = $call;
            }
        }

        // handle, make, getAllTables, first, second, nested, NewInstance
        self::assertCount(7, $calls);
    }

    public function test_statement_call_finder_finds_chained_receiver(): void
    {
        $statement = $this->firstStatement('$table->geometry("geo", "point")->change();');

        $calls = $this->callFinder->find($statement);

        self::assertCount(2, $calls, 'both geometry() and change() are found');
    }

    public function test_argument_helper_detects_named_and_unpack_arguments(): void
    {
        $named = $this->firstStatement('$query->where("col", "=", 1);')->expr;
        self::assertInstanceOf(MethodCall::class, $named);
        self::assertFalse($this->argumentHelper->hasNamedArguments($named));

        $spread = $this->firstStatement('$query->where(...$parts);')->expr;
        self::assertInstanceOf(MethodCall::class, $spread);
        self::assertTrue($this->argumentHelper->hasUnpack($spread));
        self::assertTrue($this->argumentHelper->hasNamedArguments($spread));
    }

    public function test_argument_helper_arg_by_name_or_position(): void
    {
        $positional = $this->firstStatement('$cache->touch("key", 60);')->expr;
        self::assertInstanceOf(MethodCall::class, $positional);

        $key = $this->argumentHelper->argByNameOrPosition($positional, 0);
        self::assertNotNull($key);
        self::assertNull($key->name);

        $named = $this->firstStatement('$carbon->diffInDays($other, absolute: false);')->expr;
        self::assertInstanceOf(MethodCall::class, $named);

        $absolute = $this->argumentHelper->argByNameOrPosition($named, null, 'absolute');
        self::assertNotNull($absolute);
        self::assertNotNull($absolute->name);

        // A named argument does not occupy a positional slot.
        self::assertNull($this->argumentHelper->argByNameOrPosition($named, 1));
        self::assertSame($absolute, $this->argumentHelper->argByNameOrPosition($named, null, 'absolute'));
    }

    /**
     * @return list<Stmt>
     */
    private function parse(string $code): array
    {
        return $this->parser->parse($code) ?? [];
    }

    private function firstStatement(string $code): Expression
    {
        $statements = $this->parser->parse('<?php '.$code);

        $statement = $statements[0] ?? null;
        self::assertInstanceOf(Expression::class, $statement);

        return $statement;
    }
}
