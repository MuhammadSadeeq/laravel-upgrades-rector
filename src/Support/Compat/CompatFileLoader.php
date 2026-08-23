<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\Compat;

/**
 * Loads and validates JSON data files from resources/compat/.
 */
final class CompatFileLoader
{
    /**
     * @return array<string, mixed>
     */
    public static function load(string $filePath, string $rootKey): array
    {
        if (! is_file($filePath)) {
            throw new CompatFileNotFoundException(sprintf(
                'Compatibility data file "%s" was not found. A re-installed package should always ship it.',
                $filePath
            ));
        }

        $raw = file_get_contents($filePath);

        if ($raw === false) {
            throw new CompatFileNotFoundException(sprintf('Could not read compatibility data file "%s".', $filePath));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            throw new CompatFileNotFoundException(sprintf(
                'Compatibility data file "%s" contains invalid JSON: %s',
                $filePath,
                $jsonException->getMessage()
            ));
        }

        if (! is_array($decoded)) {
            throw new CompatFileNotFoundException(sprintf(
                'Compatibility data file "%s" must contain a JSON object.',
                $filePath
            ));
        }

        if ($rootKey === '') {
            /** @var array<string, mixed> $decoded */
            return $decoded;
        }

        $section = $decoded[$rootKey] ?? null;

        if (! is_array($section)) {
            throw new CompatFileNotFoundException(sprintf(
                'Compatibility data file "%s" is missing the "%s" section.',
                $filePath,
                $rootKey
            ));
        }

        /** @var array<string, mixed> $section */
        return $section;
    }
}
