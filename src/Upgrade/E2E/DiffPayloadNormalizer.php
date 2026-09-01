<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\E2E;

/**
 * Normalizes whitespace-only unified diff payloads without damaging content
 * lines. Addition/deletion markers remain visible; an empty context payload
 * is serialized as an empty line because a context marker is itself a
 * trailing space in a snapshot file.
 */
final class DiffPayloadNormalizer
{
    public static function normalize(string $diff): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $diff));

        foreach ($lines as $index => $line) {
            $marker = $line[0] ?? '';

            if (in_array($marker, ['+', '-', ' '], true)) {
                $payload = substr($line, 1);

                if (trim($payload, " \t") === '') {
                    $lines[$index] = $marker === ' ' ? '' : $marker;

                    continue;
                }

                $lines[$index] = $marker.$payload;

                continue;
            }

            $lines[$index] = $line;
        }

        return implode("\n", $lines);
    }
}
