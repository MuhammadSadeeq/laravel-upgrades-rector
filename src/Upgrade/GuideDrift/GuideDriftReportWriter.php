<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift;

use RuntimeException;

/** Renders and safely persists the deterministic JSON/Markdown drift report. */
final class GuideDriftReportWriter
{
    /**
     * @param  array<string, mixed>  $report
     * @return array{json: string, markdown: string}
     */
    public function write(array $report, string $output): array
    {
        [$jsonPath, $markdownPath] = $this->paths($output);
        SafePath::assertNoSymlinkComponents($this->absolute($jsonPath), 'report path');
        SafePath::assertNoSymlinkComponents($this->absolute($markdownPath), 'report path');
        $this->writeFile($jsonPath, self::json($report));
        $this->writeFile($markdownPath, self::markdown($report));

        return ['json' => $jsonPath, 'markdown' => $markdownPath];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function json(array $report): string
    {
        return json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ).PHP_EOL;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function markdown(array $report): string
    {
        $status = is_string($report['status'] ?? null) ? $report['status'] : 'error';
        $markdown = "# Guide drift report\n\n";
        $markdown .= 'Status: **'.self::escape($status)."**.\n\n";
        $drift = is_array($report['drift'] ?? null) ? $report['drift'] : [];

        $markdown .= "## Drift\n\n";

        if ($drift === []) {
            $markdown .= "No meaningful heading or skeleton-tag drift detected.\n\n";
        } else {
            foreach ($drift as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $type = $item['type'] ?? 'unknown';

                if ($type === 'guide-headings') {
                    $target = self::escape($item['target'] ?? 'unknown');
                    $markdown .= '### '.str_replace('laravel-', 'Laravel ', $target)." guide headings\n\n";
                    self::headingLines($markdown, 'Added', $item['added'] ?? []);
                    self::headingLines($markdown, 'Removed', $item['removed'] ?? []);
                    $markdown .= "\n";

                    continue;
                }

                if ($type === 'skeleton-tag' || $type === 'skeleton-tag-new-major') {
                    $major = self::escape($item['major'] ?? '?');
                    $latest = self::escape($item['latestTag'] ?? '?');

                    if ($type === 'skeleton-tag') {
                        $snapshot = self::escape($item['snapshotTag'] ?? '?');
                        $markdown .= sprintf("- Laravel %s skeleton tag advanced from `%s` to `%s`.\n", $major, $snapshot, $latest);
                    } else {
                        $markdown .= sprintf("- Laravel %s has a newer skeleton tag `%s`; add a snapshot before enabling support.\n", $major, $latest);
                    }
                }
            }

            $markdown .= "\n";
        }

        $errors = is_array($report['errors'] ?? null) ? $report['errors'] : [];
        $markdown .= "## Errors\n\n";

        if ($errors === []) {
            $markdown .= "None.\n\n";
        } else {
            foreach ($errors as $error) {
                $markdown .= '- '.self::escape($error)."\n";
            }

            $markdown .= "\n";
        }

        $markdown .= "## Sources\n\n";
        $sources = is_array($report['sources'] ?? null) ? $report['sources'] : [];

        foreach ($sources as $name => $source) {
            if (! is_array($source)) {
                continue;
            }

            $url = self::escape($source['url'] ?? '');
            $snapshot = self::escape($source['snapshot'] ?? '');
            $markdown .= sprintf("- `%s`: [%s](%s), snapshot `%s`\n", self::escape($name), $url, $url, $snapshot);
        }

        return $markdown."\n";
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function paths(string $output): array
    {
        if ($output === '') {
            throw new RuntimeException('The report output path cannot be empty.');
        }

        $extension = strtolower(pathinfo($output, PATHINFO_EXTENSION));

        if ($extension === 'json' || $extension === 'md') {
            $directory = dirname($output);
            $stem = pathinfo($output, PATHINFO_FILENAME);

            if ($stem === '') {
                throw new RuntimeException(sprintf('The report output path "%s" has no file name.', $output));
            }

            if ($extension === 'json') {
                return [$output, $directory.'/'.$stem.'.md'];
            }

            return [$directory.'/'.$stem.'.json', $output];
        }

        $directory = rtrim($output, '/');

        if ($directory === '') {
            throw new RuntimeException('The report output directory cannot be the filesystem root.');
        }

        return [$directory.'/guide-drift.json', $directory.'/guide-drift.md'];
    }

    private function writeFile(string $path, string $contents): void
    {
        $absolute = $this->absolute($path);
        $directory = dirname($absolute);

        SafePath::assertNoSymlinkComponents($absolute, 'report path');

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create report directory "%s".', $directory));
        }

        $resolvedDirectory = realpath($directory);

        if ($resolvedDirectory === false || ! is_dir($resolvedDirectory)) {
            throw new RuntimeException(sprintf('Could not resolve report directory "%s".', $directory));
        }

        $absolute = $resolvedDirectory.'/'.basename($absolute);
        SafePath::assertNoSymlinkComponents($absolute, 'report path');

        $temporary = tempnam($directory, '.guide-drift-');

        if ($temporary === false) {
            throw new RuntimeException(sprintf('Could not create temporary report "%s".', $path));
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $absolute)) {
                throw new RuntimeException(sprintf('Could not atomically write report "%s".', $path));
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function absolute(string $path): string
    {
        $absolute = str_starts_with($path, '/') ? $path : getcwd().'/'.$path;
        $parts = explode('/', trim($absolute, '/'));
        $current = '/';

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                throw new RuntimeException(sprintf('Unsafe report path "%s".', $path));
            }

            $current .= ($current === '/' ? '' : '/').$part;

        }

        return $current;
    }

    private static function escape(mixed $value): string
    {
        if (! is_scalar($value) && $value !== null) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }

        return str_replace(["\r", "\n"], ' ', (string) $value);
    }

    private static function headingLines(string &$markdown, string $label, mixed $values): void
    {
        if (! is_array($values) || $values === []) {
            return;
        }

        $markdown .= '**'.$label.':** '.implode(', ', array_map(static fn (mixed $value): string => '`'.self::escape($value).'`', $values))."\n";
    }
}
