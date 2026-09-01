<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Project;

use PhpParser\Error;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Reads the small, stable subset of Laravel config needed by the inspector.
 *
 * Config files are parsed as PHP syntax but never included or evaluated. Only
 * literal strings and the conventional env('KEY', 'default') form are
 * resolved; arbitrary expressions are deliberately reported as unknown.
 */
final class ProjectConfigReader
{
    private readonly Parser $parser;

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory)->createForNewestSupportedVersion();
    }

    /**
     * @param  array<string, string>  $environment
     * @return array{drivers: list<string>, default: ?string}
     */
    public function database(?string $contents, array $environment): array
    {
        $config = $this->parseReturnArray($contents);

        if ($config === null) {
            return ['drivers' => [], 'default' => null];
        }

        $default = $this->resolve($this->value($config, 'default'), 'DB_CONNECTION', $environment);
        $drivers = [];
        $connections = $this->value($config, 'connections');

        if ($connections instanceof Array_) {
            foreach ($connections->items as $connection) {
                if (! $connection instanceof ArrayItem || ! $connection->value instanceof Array_) {
                    continue;
                }

                $driver = $this->resolve($this->value($connection->value, 'driver'), 'DB_CONNECTION', $environment);

                if ($driver !== null) {
                    $drivers[] = $driver;
                }
            }
        }

        if ($default !== null) {
            $drivers[] = $default;
        }

        return ['drivers' => $drivers, 'default' => $default];
    }

    /**
     * @param  array<string, string>  $environment
     */
    public function queueDefault(?string $contents, array $environment): ?string
    {
        return $this->configValue($contents, 'default', 'QUEUE_CONNECTION', $environment);
    }

    /**
     * @param  array<string, string>  $environment
     * @return array{driver: ?string, serialization: ?string}
     */
    public function session(?string $contents, array $environment): array
    {
        return [
            'driver' => $this->configValue($contents, 'driver', 'SESSION_DRIVER', $environment),
            'serialization' => $this->configValue($contents, 'serialization', 'SESSION_SERIALIZATION', $environment),
        ];
    }

    /** @param array<string, string> $environment */
    private function configValue(?string $contents, string $key, string $environmentKey, array $environment): ?string
    {
        $config = $this->parseReturnArray($contents);

        return $config === null ? null : $this->resolve($this->value($config, $key), $environmentKey, $environment);
    }

    /** @param array<string, string> $environment */
    private function resolve(?Expr $expression, string $environmentKey, array $environment): ?string
    {
        if ($expression instanceof String_) {
            return $expression->value;
        }

        if (! $expression instanceof FuncCall
            || ! $expression->name instanceof Name
            || $expression->name->toString() !== 'env'
            || count($expression->args) < 1
            || count($expression->args) > 2
            || ! $expression->args[0] instanceof Arg
            || $expression->args[0]->name !== null
            || ! $expression->args[0]->value instanceof String_
            || $expression->args[0]->value->value !== $environmentKey) {
            return null;
        }

        if (array_key_exists($environmentKey, $environment)) {
            return $environment[$environmentKey];
        }

        $default = $expression->args[1] ?? null;

        return $default instanceof Arg && $default->name === null && $default->value instanceof String_
            ? $default->value->value
            : null;
    }

    private function parseReturnArray(?string $contents): ?Array_
    {
        if ($contents === null) {
            return null;
        }

        try {
            $statements = $this->parser->parse($contents);
        } catch (Error) {
            return null;
        }

        if ($statements === null) {
            return null;
        }

        foreach ($statements as $statement) {
            if ($statement instanceof Return_ && $statement->expr instanceof Array_) {
                return $statement->expr;
            }
        }

        return null;
    }

    private function value(Array_ $array, string $key): ?Expr
    {
        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem
                && $item->key instanceof String_
                && $item->key->value === $key) {
                return $item->value;
            }
        }

        return null;
    }
}
