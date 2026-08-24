<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Shared;

/**
 * One contract-method addition, expressed as verbatim PHP-verified code.
 *
 * The `definition` is the exact method text (signature + body) that the real
 * framework's interface expects — every shipped definition was checked
 * against laravel/framework sources, so the data file is the single point of
 * truth for new Laravel versions (decision D4).
 */
final class ContractMethodSpec
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['interface', 'method', 'definition'] as $required) {
            if (! isset($data[$required]) || ! is_string($data[$required])) {
                throw new \InvalidArgumentException(sprintf(
                    'Contract method specification is missing the "%s" key.',
                    $required
                ));
            }
        }

        return new self(
            $data['interface'],
            $data['method'],
            $data['definition'],
            isset($data['todo']) && is_string($data['todo'])
                ? $data['todo']
                : sprintf('implement %s() to satisfy the updated contract.', $data['method']),
            isset($data['definition_eloquent']) && is_string($data['definition_eloquent'])
                ? $data['definition_eloquent']
                : null,
            isset($data['todo_eloquent']) && is_string($data['todo_eloquent'])
                ? $data['todo_eloquent']
                : null
        );
    }

    public function __construct(
        public readonly string $interface,
        public readonly string $method,
        public readonly string $definition,
        public readonly string $todo,
        public readonly ?string $definitionEloquent,
        public readonly ?string $todoEloquent,
    ) {}
}
