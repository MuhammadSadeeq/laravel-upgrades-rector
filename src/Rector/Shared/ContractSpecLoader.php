<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Shared;

use RuntimeException;

/**
 * Loads the per-major contract addition lists from
 * resources/contracts/laravel-<major>.php (decision D4).
 */
final class ContractSpecLoader
{
    /**
     * @return list<ContractMethodSpec>
     */
    public static function forMajor(int $major): array
    {
        $file = dirname(__DIR__, 3).'/resources/contracts/laravel-'.$major.'.php';

        if (! is_file($file)) {
            throw new RuntimeException(sprintf(
                'Contract data file "%s" was not found.',
                $file
            ));
        }

        /** @var mixed $entries */
        $entries = require $file;

        if (! is_array($entries)) {
            throw new RuntimeException(sprintf(
                'Contract data file "%s" must return a list.',
                $file
            ));
        }

        $specs = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            /** @var array<string, mixed> $entry */
            $specs[] = ContractMethodSpec::fromArray($entry);
        }

        return $specs;
    }
}
