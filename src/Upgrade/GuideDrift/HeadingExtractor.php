<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift;

use RuntimeException;

/** Extracts stable, level-aware heading tokens from Markdown or HTML. */
final class HeadingExtractor
{
    /**
     * @return list<string> Tokens are formatted as "<level>:<normalised text>".
     */
    public static function extract(string $contents): array
    {
        $contents = ltrim($contents, "\xEF\xBB\xBF");

        if (trim($contents) === '') {
            throw new RuntimeException('A guide source is empty.');
        }

        if (self::isHtmlDocument($contents)) {
            return self::extractHtml($contents);
        }

        return self::extractMarkdown($contents);
    }

    /**
     * @return list<string>
     */
    private static function extractMarkdown(string $contents): array
    {
        $lines = preg_split('/\R/u', $contents) ?: [];
        $headings = [];
        $fenced = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s{0,3}(```+|~~~+)/', $line, $fence) === 1) {
                $fenced = ! $fenced;

                continue;
            }

            if ($fenced) {
                continue;
            }

            if (preg_match('/^\s{0,3}(#{1,6})(?:\s+|$)(.*?)\s*$/u', $line, $matches) === 1) {
                $token = self::token(strlen($matches[1]), $matches[2]);

                if ($token !== null) {
                    $headings[] = $token;
                }

                continue;
            }

            if ($index === 0 || trim($line) === '' || ! isset($lines[$index - 1])) {
                continue;
            }

            $previous = trim($lines[$index - 1]);

            if ($previous === '' || preg_match('/^\s*(=+|-+)\s*$/', $line, $underline) !== 1) {
                continue;
            }

            $level = $underline[1][0] === '=' ? 1 : 2;
            $token = self::token($level, $previous);

            if ($token !== null) {
                $headings[] = $token;
            }
        }

        return self::unique($headings);
    }

    /**
     * @return list<string>
     */
    private static function extractHtml(string $contents): array
    {
        // Restrict extraction to article/main when available so navigation
        // headings do not cause false positives on the Carbon site.
        if (preg_match('/<(?:main|article)\b[^>]*>(.*?)<\/(?:main|article)>/is', $contents, $container) === 1) {
            $contents = $container[1];
        }

        $contents = preg_replace('/<(?:script|style)\b[^>]*>.*?<\/(?:script|style)>/is', '', $contents) ?? $contents;
        $headings = [];

        if (preg_match_all('/<h([1-6])\b[^>]*>(.*?)<\/h\1\s*>/is', $contents, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $text = preg_replace('/<a\b[^>]*>\s*(?:<[^>]+>\s*)*#\s*(?:<\/[^>]+>\s*)*<\/a>/is', '', $match[2]) ?? $match[2];
            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $token = self::token((int) $match[1], $text);

            if ($token !== null) {
                $headings[] = $token;
            }
        }

        return self::unique($headings);
    }

    private static function isHtmlDocument(string $contents): bool
    {
        // A Markdown guide may show HTML in a fenced example. Only inspect
        // lines outside fences when looking for document/container signals.
        $lines = preg_split('/\R/u', $contents) ?: [];
        $outsideFence = [];
        $fenced = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s{0,3}(```+|~~~+)/', $line) === 1) {
                $fenced = ! $fenced;

                continue;
            }

            if (! $fenced) {
                $outsideFence[] = $line;
            }
        }

        $outsideFenceContents = implode("\n", $outsideFence);

        return preg_match('/(?:<!doctype\s+html\b|<html\b|<head\b|<body\b|<(?:main|article)\b|<h[1-6]\b)/i', $outsideFenceContents) === 1;
    }

    private static function token(int $level, string $heading): ?string
    {
        $heading = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $heading) ?? $heading;
        $heading = preg_replace('/\s+#+\s*$/u', '', $heading) ?? $heading;
        $heading = preg_replace('/\s*\{#[^}]+\}\s*$/u', '', $heading) ?? $heading;
        $heading = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $heading) ?? $heading;
        $heading = preg_replace('/`+/u', '', $heading) ?? $heading;
        // Remove paired emphasis/strike delimiters, but keep identifier
        // punctuation such as `foo_bar` and `cache*key` intact.
        $heading = preg_replace('/(?<![\p{L}\p{N}_])(\*{1,3}|_{1,3}|~{2})(?=\S)(.*?\S)\1(?![\p{L}\p{N}_])/u', '$2', $heading) ?? $heading;
        $heading = preg_replace('/<[^>]+>/u', '', $heading) ?? $heading;
        $heading = html_entity_decode($heading, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $heading = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $heading) ?? $heading;
        $heading = preg_replace('/\s+/u', ' ', trim($heading)) ?? trim($heading);

        if ($heading === '') {
            return null;
        }

        $heading = function_exists('mb_strtolower')
            ? mb_strtolower($heading, 'UTF-8')
            : strtolower($heading);

        return $level.':'.$heading;
    }

    /**
     * @param  list<string>  $headings
     * @return list<string>
     */
    private static function unique(array $headings): array
    {
        /** @var list<string> $unique */
        $unique = array_values(array_unique($headings));

        return $unique;
    }
}
