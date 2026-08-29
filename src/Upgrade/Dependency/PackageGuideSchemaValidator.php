<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use JsonException;
use RuntimeException;

/**
 * Small executable subset of JSON Schema used by package-guides.json.
 *
 * The package intentionally does not add a JSON-schema dependency for one
 * resource file. This validator covers every keyword used by the shipped
 * schema (types, required/properties, strict objects, const, enum, patterns,
 * propertyNames, size bounds, and the conditional `if`/`then` subset) and is
 * also used by the parity test.
 */
final class PackageGuideSchemaValidator
{
    public function validate(string $dataPath, string $schemaPath): void
    {
        $data = $this->decode($dataPath);
        $schema = $this->decode($schemaPath);
        $this->validateValue($data, $schema, '$');
    }

    /** @return array<string, mixed> */
    private function decode(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(sprintf('Could not read package guide schema resource "%s".', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Could not read package guide schema resource "%s".', $path));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('Invalid JSON in package guide resource "%s".', $path), 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('Package guide resource "%s" must contain a JSON object.', $path));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** @param array<string, mixed> $schema */
    private function validateValue(mixed $value, array $schema, string $path): void
    {
        // Keep the conditional subset deliberately small, but execute it
        // rather than silently ignoring it. The shipped guide schema uses it
        // to require notes whenever a major is marked as future.
        $allOf = $schema['allOf'] ?? null;

        if (is_array($allOf)) {
            foreach ($allOf as $index => $subSchema) {
                if (! is_array($subSchema)) {
                    throw $this->invalid($path.'.allOf['.$index.']', 'must be a schema object');
                }

                $subSchemaMap = $this->schemaMap($subSchema);

                if ($subSchemaMap === null) {
                    throw $this->invalid($path.'.allOf['.$index.']', 'must be a schema object');
                }

                $this->validateValue($value, $subSchemaMap, $path);
            }
        }

        $if = $schema['if'] ?? null;
        $then = $schema['then'] ?? null;

        if ($if !== null || $then !== null) {
            if (! is_array($if) || ! is_array($then)) {
                throw $this->invalid($path, 'if and then must be schema objects');
            }

            $ifSchema = $this->schemaMap($if);
            $thenSchema = $this->schemaMap($then);

            if ($ifSchema === null || $thenSchema === null) {
                throw $this->invalid($path, 'if and then must be schema objects');
            }

            if ($this->matches($value, $ifSchema, $path.'.if')) {
                $this->validateValue($value, $thenSchema, $path.'.then');
            }
        }

        $type = $schema['type'] ?? null;

        if (is_string($type) && ! $this->isType($value, $type)) {
            throw $this->invalid($path, sprintf('must be %s', $type));
        }

        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            throw $this->invalid($path, 'does not equal the required constant');
        }

        if (is_array($schema['enum'] ?? null) && ! in_array($value, $schema['enum'], true)) {
            throw $this->invalid($path, 'is not an allowed value');
        }

        if (is_string($schema['pattern'] ?? null)
            && (! is_string($value) || preg_match('~'.$schema['pattern'].'~', $value) !== 1)) {
            throw $this->invalid($path, 'does not match the required pattern');
        }

        if (is_int($schema['minLength'] ?? null)
            && (! is_string($value) || strlen($value) < $schema['minLength'])) {
            throw $this->invalid($path, sprintf('must contain at least %d characters', $schema['minLength']));
        }

        if (is_array($value)) {
            $schemaType = $schema['type'] ?? null;
            $hasObjectKeywords = array_key_exists('required', $schema)
                || array_key_exists('properties', $schema)
                || array_key_exists('additionalProperties', $schema)
                || array_key_exists('propertyNames', $schema)
                || array_key_exists('minProperties', $schema);

            if ($schemaType === 'object'
                || ($schemaType === null && $hasObjectKeywords && ! array_is_list($value))) {
                $this->validateObject($value, $schema, $path);
            } else {
                $this->validateArray($value, $schema, $path);
            }
        }
    }

    /** @param array<array-key, mixed> $value
     * @param  array<string, mixed>  $schema
     */
    private function validateArray(array $value, array $schema, string $path): void
    {
        if (($schema['type'] ?? null) === 'object') {
            $this->validateObject($value, $schema, $path);
        }

        if (($schema['type'] ?? null) === 'array') {
            $minimum = $schema['minItems'] ?? null;

            if (is_int($minimum) && count($value) < $minimum) {
                throw $this->invalid($path, sprintf('must contain at least %d items', $minimum));
            }

            $itemSchema = $this->schemaMap($schema['items'] ?? null);

            if ($itemSchema !== null) {
                foreach ($value as $index => $item) {
                    $this->validateValue($item, $itemSchema, $path.'['.$index.']');
                }
            }
        }
    }

    /** @param array<array-key, mixed> $value
     * @param  array<string, mixed>  $schema
     */
    private function validateObject(array $value, array $schema, string $path): void
    {
        $minimum = $schema['minProperties'] ?? null;

        if (is_int($minimum) && count($value) < $minimum) {
            throw $this->invalid($path, sprintf('must contain at least %d properties', $minimum));
        }

        $properties = $this->schemaMap($schema['properties'] ?? null) ?? [];

        $requiredProperties = $schema['required'] ?? [];

        if (! is_array($requiredProperties)) {
            $requiredProperties = [];
        }

        foreach ($requiredProperties as $required) {
            if (is_string($required) && ! array_key_exists($required, $value)) {
                throw $this->invalid($path, sprintf('is missing required property "%s"', $required));
            }
        }

        $additionalProperties = $schema['additionalProperties'] ?? true;

        if ($additionalProperties === false) {
            foreach (array_keys($value) as $key) {
                if (! is_string($key) || ! array_key_exists($key, $properties)) {
                    throw $this->invalid($path, sprintf('contains unsupported property "%s"', (string) $key));
                }
            }
        }

        $propertyNames = $schema['propertyNames'] ?? null;

        $propertyNameSchema = $this->schemaMap($propertyNames);

        if ($propertyNameSchema !== null) {
            foreach (array_keys($value) as $key) {
                $this->validateValue((string) $key, $propertyNameSchema, $path.' property name');
            }
        }

        foreach ($value as $key => $child) {
            // json_decode(..., true) converts object property names made only
            // of digits (the major keys) to integer array keys. Numeric keys
            // are still object properties here because this method is reached
            // only after the enclosing schema accepted `type: object`.
            $propertyName = (string) $key;

            $propertySchema = array_key_exists($propertyName, $properties)
                ? $this->schemaMap($properties[$propertyName])
                : (is_array($additionalProperties) ? $this->schemaMap($additionalProperties) : null);

            if ($propertySchema !== null) {
                $this->validateValue($child, $propertySchema, $path.'.'.$propertyName);
            }
        }
    }

    /** @param array<string, mixed> $schema */
    private function matches(mixed $value, array $schema, string $path): bool
    {
        try {
            $this->validateValue($value, $schema, $path);

            return true;
        } catch (RuntimeException) {
            // A failed `if` condition means the conditional branch does not
            // apply. Validation errors from `then` are intentionally allowed
            // to propagate from validateValue above.
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    private function schemaMap(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $map = [];

        foreach ($value as $key => $child) {
            if (! is_string($key)) {
                return null;
            }

            $map[$key] = $child;
        }

        return $map;
    }

    private function isType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => is_array($value) && ! array_is_list($value),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => throw new RuntimeException(sprintf('Unsupported package guide schema type "%s".', $type)),
        };
    }

    private function invalid(string $path, string $message): RuntimeException
    {
        return new RuntimeException(sprintf('Package guide schema validation failed at %s: %s.', $path, $message));
    }
}
