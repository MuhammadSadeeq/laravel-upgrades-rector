<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory;

/**
 * Writes one safe, idempotent advisory comment into a PHP source file.
 *
 * Findings can come from PHPStan (absolute paths) or project scans (relative
 * paths), so both forms are accepted after being constrained to the project
 * root. No non-PHP file, missing path, invalid line, or path traversal can be
 * modified by this service.
 */
final class FindingAnnotator
{
    private readonly string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $root = realpath($projectRoot);
        $this->projectRoot = rtrim($root === false ? $projectRoot : $root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    }

    public function annotate(string $file, int $line, string $ruleId, string $message): bool
    {
        if ($line < 1 || $ruleId === '') {
            return false;
        }

        $path = $this->resolvePath($file);

        if ($path === null || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php' || ! is_file($path)) {
            return false;
        }

        return $this->annotateResolved($path, $line, $ruleId, $message);
    }

    /**
     * Annotate a collection of PHPStan-style findings.
     *
     * Findings in a single file are applied from the bottom upwards, so an
     * inserted comment cannot change the original line number of a later
     * finding. The marker position is also used to account for an earlier
     * batch, allowing a repeated call with the same findings to be a no-op.
     *
     * @param  list<array<string, mixed>>  $findings
     */
    public function annotateBatch(array $findings): int
    {
        /** @var array<string, list<array{line: int, ruleId: string, message: string}>> $byFile */
        $byFile = [];

        foreach ($findings as $finding) {
            $file = is_string($finding['file'] ?? null) ? $finding['file'] : '';
            $line = is_int($finding['line'] ?? null) ? $finding['line'] : 0;
            $ruleId = is_string($finding['ruleId'] ?? null) ? $finding['ruleId'] : '';
            $message = is_string($finding['message'] ?? null) ? $finding['message'] : '';

            if ($line < 1 || $ruleId === '') {
                continue;
            }

            $path = $this->resolvePath($file);

            if ($path === null || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php' || ! is_file($path)) {
                continue;
            }

            $byFile[$path][] = ['line' => $line, 'ruleId' => $ruleId, 'message' => $message];
        }

        $annotated = 0;

        foreach ($byFile as $path => $fileFindings) {
            usort($fileFindings, static function (array $left, array $right): int {
                return $right['line'] <=> $left['line']
                    ?: strcmp($left['ruleId'], $right['ruleId']);
            });

            /** @var array<string, true> $seen */
            $seen = [];
            $ruleIds = array_values(array_unique(array_map(
                fn (array $finding): string => $this->sanitizeRuleId($finding['ruleId']),
                $fileFindings
            )));

            foreach ($fileFindings as $finding) {
                $safeRuleId = $this->sanitizeRuleId($finding['ruleId']);
                $key = $safeRuleId."\0".$finding['line'];

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $contents = file_get_contents($path);

                if ($contents === false) {
                    continue;
                }

                $markerLines = $this->markerLines($contents, $ruleIds);
                $adjustedLine = $finding['line'] + count(array_filter(
                    $markerLines,
                    static fn (mixed $markerLine): bool => is_int($markerLine) && $markerLine < $finding['line']
                ));

                if ($this->annotateResolved($path, $adjustedLine, $finding['ruleId'], $finding['message'])) {
                    $annotated++;
                }
            }
        }

        return $annotated;
    }

    private function resolvePath(string $file): ?string
    {
        if ($file === '') {
            return null;
        }

        $candidate = $this->isAbsolute($file)
            ? $file
            : $this->projectRoot.$file;
        $real = realpath($candidate);

        if ($real === false || ! $this->isWithinRoot($real)) {
            return null;
        }

        return $real;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':');
    }

    private function isWithinRoot(string $path): bool
    {
        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR);
        $normalized = str_replace('\\', '/', $path);
        $normalizedRoot = str_replace('\\', '/', $root);

        return $normalized === $normalizedRoot || str_starts_with($normalized, $normalizedRoot.'/');
    }

    private function sanitizeRuleId(string $ruleId): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.:-]+/', '-', $ruleId);
        $safe = trim(is_string($safe) ? $safe : '', '-');

        return $safe !== '' ? $safe : 'finding';
    }

    private function annotateResolved(string $path, int $line, string $ruleId, string $message): bool
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        $safeRuleId = $this->sanitizeRuleId($ruleId);
        $marker = '// TODO(laravel-upgrade:'.$safeRuleId.'):';
        preg_match_all('/[^\r\n]*(?:\r\n|\r|\n|$)/', $contents, $lineMatches);
        $lines = array_values(array_filter(
            $lineMatches[0],
            static fn (string $line): bool => $line !== ''
        ));

        if (! isset($lines[$line - 1]) || $this->hasMarkerAtLocation($lines, $line, $marker)) {
            return false;
        }

        $target = $lines[$line - 1];
        $indent = preg_match('/^[ \t]*/', $target, $match) === 1 ? $match[0] : '';
        $eol = $this->lineEnding($target) ?? $this->defaultLineEnding($contents);
        $safeMessage = $this->sanitizeMessage($message);
        $lines[$line - 1] = $indent.$marker.' '.$safeMessage.$eol.$target;
        $updated = implode('', $lines);

        return file_put_contents($path, $updated) === strlen($updated);
    }

    /** @param list<string> $lines */
    private function hasMarkerAtLocation(array $lines, int $line, string $marker): bool
    {
        // A marker is normally the target line after a previous insertion;
        // accepting the immediately preceding line covers callers that pass
        // the target's current (shifted) line number.
        return str_contains($lines[$line - 1] ?? '', $marker)
            || str_contains($lines[$line - 2] ?? '', $marker);
    }

    /**
     * @param  list<string>  $ruleIds
     * @return list<int>
     */
    private function markerLines(string $contents, array $ruleIds): array
    {
        preg_match_all('/[^\r\n]*(?:\r\n|\r|\n|$)/', $contents, $lineMatches);
        $lines = array_values(array_filter(
            $lineMatches[0],
            static fn (string $line): bool => $line !== ''
        ));
        /** @var list<int> $markerLines */
        $markerLines = [];

        foreach ($lines as $index => $line) {
            foreach ($ruleIds as $ruleId) {
                if (str_contains($line, '// TODO(laravel-upgrade:'.$ruleId.')')) {
                    $markerLines[] = $index + 1;
                    break;
                }
            }
        }

        return $markerLines;
    }

    private function sanitizeMessage(string $message): string
    {
        $message = preg_replace('/\R+/', ' ', $message);
        $message = is_string($message) ? trim($message) : '';

        return str_replace(['/*', '*/'], ['', ''], $message);
    }

    private function lineEnding(string $line): ?string
    {
        if (str_ends_with($line, "\r\n")) {
            return "\r\n";
        }

        if (str_ends_with($line, "\n")) {
            return "\n";
        }

        if (str_ends_with($line, "\r")) {
            return "\r";
        }

        return null;
    }

    private function defaultLineEnding(string $contents): string
    {
        return str_contains($contents, "\r\n") ? "\r\n" : "\n";
    }
}
