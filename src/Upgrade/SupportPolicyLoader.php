<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/** Loads and validates the machine-readable support policy document. */
final class SupportPolicyLoader
{
    public static function load(string $path = SupportPolicy::DEFAULT_PATH): SupportPolicy
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(sprintf('Support policy file "%s" was not found or is not readable.', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            throw new RuntimeException(sprintf('Support policy file "%s" is empty or could not be read.', $path));
        }

        try {
            $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('Support policy file "%s" contains invalid JSON: %s', $path, $exception->getMessage()), 0, $exception);
        }

        if (! is_array($document) || array_is_list($document)) {
            throw new InvalidArgumentException('Support policy JSON must contain a top-level object.');
        }

        return SupportPolicy::fromArray($document);
    }

    public static function fromFile(string $path): SupportPolicy
    {
        return self::load($path);
    }
}
