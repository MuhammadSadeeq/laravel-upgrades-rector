<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\GuideDrift;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\FixtureSourceFetcher;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\GuideDriftChecker;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\GuideDriftReportWriter;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\HeadingExtractor;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\HttpSourceFetcher;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\OfflineSourceFetcher;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\SourceFetcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

final class GuideDriftCheckerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/guide-drift-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/resources/guides', 0777, true);
        mkdir($this->root.'/resources/skeletons', 0777, true);

        foreach ([11, 12, 13] as $major) {
            file_put_contents($this->root.'/resources/guides/upgrade-'.$major.'.md', $this->guide($major));
        }

        file_put_contents($this->root.'/resources/guides/carbon-3.md', $this->carbon());
        file_put_contents($this->root.'/resources/skeletons/MANIFEST.json', json_encode([
            '10' => ['tag' => 'v10.3.3'],
            '11' => ['tag' => 'v11.6.1'],
            '12' => ['tag' => 'v12.12.2'],
            '13' => ['tag' => 'v13.10.1'],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function test_clean_sources_have_no_drift(): void
    {
        $report = $this->executeCheck();

        self::assertSame('clean', $report['status']);
        self::assertSame([], $report['drift']);
        self::assertSame([], $report['errors']);
    }

    public function test_added_and_removed_headings_are_reported(): void
    {
        $report = $this->executeCheck([
            'laravel-13' => "# Laravel 13 Upgrade\n\n## Breaking changes\n\n### Added upstream\n",
        ]);

        self::assertSame('drift', $report['status']);
        self::assertSame(['### added upstream'], $this->drift($report)[0]['added']);

        file_put_contents($this->root.'/resources/guides/upgrade-13.md', "# Laravel 13 Upgrade\n\n## Removed locally\n");
        $report = $this->executeCheck();

        self::assertSame(['## breaking changes'], $this->drift($report)[0]['added']);
        self::assertSame(['## removed locally'], $this->drift($report)[0]['removed']);
    }

    public function test_body_only_changes_are_ignored(): void
    {
        $report = $this->executeCheck([
            'laravel-11' => "# Laravel 11 Upgrade\n\n## Breaking changes\n\nA completely different paragraph.\n",
        ]);

        self::assertSame('clean', $report['status']);
    }

    public function test_markdown_fenced_html_is_not_misclassified_as_html(): void
    {
        self::assertSame(
            ['1:guide', '2:breaking changes'],
            HeadingExtractor::extract("# Guide\n\n```html\n<h1>Example only</h1>\n```\n\n## Breaking changes\n"),
        );
    }

    public function test_standalone_html_heading_fragment_is_extracted(): void
    {
        self::assertSame(
            ['1:standalone title'],
            HeadingExtractor::extract('<h1>Standalone title</h1>'),
        );
    }

    public function test_heading_markup_keeps_identifier_punctuation(): void
    {
        self::assertSame(
            ['1:use foo_bar and foo_bar and foo*bar'],
            HeadingExtractor::extract('# Use **foo_bar** and foo_bar and foo*bar'),
        );
    }

    public function test_html_heading_strips_zero_width_anchor_and_anchor_marker(): void
    {
        self::assertSame(
            ['2:carbon migration'],
            HeadingExtractor::extract("<!doctype html>\n<html><main><h2>Carbon\u{200B} migration <a href=\"#carbon-migration\">#</a></h2></main></html>"),
        );
    }

    public function test_carbon_heading_drift_is_reported(): void
    {
        $report = $this->executeCheck([
            'carbon-3' => "# Carbon 3 migration\n\n## New migration section\n",
        ]);

        self::assertSame('drift', $report['status']);
        self::assertSame('carbon-3', $this->drift($report)[0]['target']);
        self::assertSame(['## new migration section'], $this->drift($report)[0]['added']);
    }

    public function test_newer_skeleton_tag_is_reported(): void
    {
        $report = $this->executeCheck([], ['v11.6.1', 'v12.12.2', 'v13.10.2']);

        self::assertSame('drift', $report['status']);
        self::assertSame('skeleton-tag', $this->drift($report)[0]['type']);
        self::assertSame('v13.10.2', $this->drift($report)[0]['latestTag']);
    }

    public function test_matching_refs_check_old_majors_even_when_many_newer_tags_exist(): void
    {
        $tags = ['v10.3.4', 'v11.6.2'];

        for ($minor = 0; $minor < 250; $minor++) {
            $tags[] = 'v13.'.$minor.'.0';
        }

        $report = $this->executeCheck([], $tags);
        $skeletonDrift = array_values(array_filter(
            $this->drift($report),
            static fn (array $item): bool => ($item['type'] ?? null) === 'skeleton-tag',
        ));

        self::assertSame('drift', $report['status']);
        self::assertSame([10, 11, 13], array_column($skeletonDrift, 'major'));
        self::assertSame('v10.3.4', $skeletonDrift[0]['latestTag']);
        self::assertSame('v11.6.2', $skeletonDrift[1]['latestTag']);
    }

    public function test_new_next_major_tag_is_reported(): void
    {
        $report = $this->executeCheck([], ['v14.0.0']);

        self::assertSame('drift', $report['status']);
        self::assertSame('skeleton-tag-new-major', $this->drift($report)[0]['type']);
        self::assertSame(14, $this->drift($report)[0]['major']);
    }

    public function test_refresh_replaces_all_guide_snapshots(): void
    {
        $fixture = $this->makeFixtureDirectory('Fixture heading', 'Fixture heading');
        $fetcher = new FixtureSourceFetcher($fixture);

        $report = (new GuideDriftChecker($this->root, $fetcher))->run(true);

        self::assertSame('clean', $report['status']);
        self::assertTrue($report['refreshed']);
        self::assertStringContainsString('Fixture heading', (string) file_get_contents($this->root.'/resources/guides/upgrade-11.md'));

        $this->removeDirectory($fixture);
    }

    public function test_cli_fixture_exits_for_clean_drift_and_operational_failures(): void
    {
        $fixture = $this->makeFixtureDirectory();
        $output = $this->root.'/cli-report';
        $binary = dirname(__DIR__, 3).'/bin/check-guide-drift';

        $clean = new Process([PHP_BINARY, $binary, '--root', $this->root, '--fixture-dir', $fixture, '--output', $output]);
        $clean->run();

        self::assertSame(0, $clean->getExitCode(), $clean->getErrorOutput());
        self::assertFileExists($output.'/guide-drift.json');

        file_put_contents($fixture.'/upgrade-13.md', "# Laravel 13 Upgrade\n\n## Added fixture heading\n");
        $drift = new Process([PHP_BINARY, $binary, '--root', $this->root, '--fixture-dir', $fixture, '--output', $output]);
        $drift->run();

        self::assertSame(1, $drift->getExitCode(), $drift->getErrorOutput());
        self::assertStringContainsString('"status": "drift"', (string) file_get_contents($output.'/guide-drift.json'));

        unlink($fixture.'/upgrade-12.md');
        $failure = new Process([PHP_BINARY, $binary, '--root', $this->root, '--fixture-dir', $fixture, '--output', $output]);
        $failure->run();

        self::assertSame(2, $failure->getExitCode(), $failure->getErrorOutput());
        self::assertStringContainsString('"status": "error"', (string) file_get_contents($output.'/guide-drift.json'));

        $this->removeDirectory($fixture);
    }

    public function test_manifest_symlink_is_rejected(): void
    {
        $manifest = $this->root.'/resources/skeletons/MANIFEST.json';
        $target = $this->root.'/manifest-target.json';
        rename($manifest, $target);
        symlink($target, $manifest);

        $report = $this->executeCheck();

        self::assertSame('error', $report['status']);
        self::assertStringContainsString('symlink', strtolower($this->error($report)));

        unlink($manifest);
        rename($target, $manifest);
    }

    public function test_fixture_and_offline_fetchers_reject_symlink_intermediate_components(): void
    {
        $fixture = $this->root.'/fixture';
        $outside = $this->root.'/outside';
        mkdir($fixture, 0777, true);
        mkdir($outside, 0777, true);
        file_put_contents($outside.'/upgrade.md', $this->guide(11));
        symlink($outside, $fixture.'/11.x');

        $fixtureFetcher = new FixtureSourceFetcher($fixture);

        try {
            $fixtureFetcher->fetch('https://raw.githubusercontent.com/laravel/docs/11.x/upgrade.md', 2_097_152);
            self::fail('Expected a symlinked fixture component to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('symlink', strtolower($exception->getMessage()));
        }
    }

    public function test_offline_fetcher_rejects_symlinked_source_directory(): void
    {
        $offlineRoot = $this->root.'/offline';
        $outside = $this->root.'/outside-offline';
        mkdir($offlineRoot.'/resources', 0777, true);
        mkdir($outside, 0777, true);
        file_put_contents($outside.'/upgrade-11.md', $this->guide(11));
        symlink($outside, $offlineRoot.'/resources/guides');

        $fetcher = new OfflineSourceFetcher($offlineRoot);

        try {
            $fetcher->fetch('https://raw.githubusercontent.com/laravel/docs/11.x/upgrade.md', 2_097_152);
            self::fail('Expected a symlinked offline component to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('symlink', strtolower($exception->getMessage()));
        }
    }

    public function test_report_writer_rejects_nested_symlink_ancestor_before_creating_directory(): void
    {
        $outside = $this->root.'/report-outside';
        $linked = $this->root.'/report-link';
        mkdir($outside, 0777, true);
        symlink($outside, $linked);

        try {
            (new GuideDriftReportWriter)->write(['status' => 'clean', 'drift' => [], 'errors' => []], $linked.'/new/nested');
            self::fail('Expected a symlinked report ancestor to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('symlink', strtolower($exception->getMessage()));
        }

        self::assertDirectoryDoesNotExist($outside.'/new');
    }

    public function test_http_fetcher_rejects_redirects_and_reports_statuses(): void
    {
        $redirect = new HttpSourceFetcher(static fn (string $url, int $maxBytes): array => [
            'status' => 302,
            'body' => '',
            'headers' => ['Location: https://example.test/next'],
        ]);

        try {
            $redirect->fetch('https://example.test/source', 1024);
            self::fail('Expected redirects to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('HTTP status 302', $exception->getMessage());
        }

        $serverError = new HttpSourceFetcher(static fn (string $url, int $maxBytes): array => [
            'status' => 503,
            'body' => 'unavailable',
        ]);

        try {
            $serverError->fetch('https://example.test/source', 1024);
            self::fail('Expected non-2xx responses to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('HTTP status 503', $exception->getMessage());
        }
    }

    public function test_http_fetcher_reports_rate_limit_diagnostics(): void
    {
        $fetcher = new HttpSourceFetcher(static fn (string $url, int $maxBytes): array => [
            'status' => 403,
            'body' => '{"message":"API rate limit exceeded"}',
            'headers' => ['X-RateLimit-Remaining: 0', 'Retry-After: 60'],
        ]);

        try {
            $fetcher->fetch('https://api.github.com/example', 1024);
            self::fail('Expected an exhausted rate limit to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('rate limit exhausted', strtolower($exception->getMessage()));
            self::assertStringContainsString('Retry after 60', $exception->getMessage());
        }
    }

    public function test_http_fetcher_enforces_response_size_limit(): void
    {
        $fetcher = new HttpSourceFetcher(static fn (string $url, int $maxBytes): array => [
            'status' => 200,
            'body' => '123456',
        ]);

        try {
            $fetcher->fetch('https://example.test/source', 5);
            self::fail('Expected an oversized response to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('byte limit', $exception->getMessage());
        }
    }

    public function test_http_fetcher_reports_transport_timeout_without_waiting(): void
    {
        $fetcher = new HttpSourceFetcher(static function (string $url, int $maxBytes): array {
            throw new RuntimeException('Operation timed out');
        });

        try {
            $fetcher->fetch('https://example.test/source', 1024);
            self::fail('Expected a transport timeout to be reported.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Operation timed out', $exception->getMessage());
        }
    }

    public function test_malformed_oversized_and_missing_sources_are_operational_errors(): void
    {
        $malformed = $this->executeCheck([], null, 'not-json');
        self::assertSame('error', $malformed['status']);
        self::assertStringContainsString('malformed JSON', $this->error($malformed));

        $oversized = $this->executeCheck([
            'laravel-11' => str_repeat('x', 2_097_153),
        ]);
        self::assertSame('error', $oversized['status']);
        self::assertStringContainsString('byte limit', $this->error($oversized));

        unlink($this->root.'/resources/guides/upgrade-12.md');
        $missing = $this->executeCheck();
        self::assertSame('error', $missing['status']);
        self::assertStringContainsString('missing', strtolower($this->error($missing)));
    }

    public function test_json_and_markdown_reports_are_stable(): void
    {
        $report = $this->executeCheck([
            'laravel-13' => "# Laravel 13 Upgrade\n\n## New heading\n",
        ]);
        $writer = new GuideDriftReportWriter;

        self::assertSame(GuideDriftReportWriter::json($report), GuideDriftReportWriter::json($report));
        self::assertSame(GuideDriftReportWriter::markdown($report), GuideDriftReportWriter::markdown($report));

        $paths = $writer->write($report, $this->root.'/report.json');
        self::assertFileExists($paths['json']);
        self::assertFileExists($paths['markdown']);
        self::assertSame(GuideDriftReportWriter::json($report), file_get_contents($paths['json']));
        self::assertStringContainsString('new heading', (string) file_get_contents($paths['markdown']));
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<array<string, mixed>>
     */
    private function drift(array $report): array
    {
        $drift = $report['drift'] ?? [];

        if (! is_array($drift)) {
            return [];
        }

        $items = [];

        foreach ($drift as $item) {
            if (is_array($item)) {
                $normalized = [];

                foreach ($item as $key => $value) {
                    $normalized[(string) $key] = $value;
                }

                $items[] = $normalized;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function error(array $report): string
    {
        $errors = $report['errors'] ?? [];

        return is_array($errors) && is_string($errors[0] ?? null) ? $errors[0] : '';
    }

    /**
     * @param  array<string, string>  $sources
     * @param  list<string>|null  $tags
     * @return array<string, mixed>
     */
    private function executeCheck(array $sources = [], ?array $tags = null, ?string $rawTags = null): array
    {
        $tags = array_merge(['v10.3.3', 'v11.6.1', 'v12.12.2', 'v13.10.1'], $tags ?? []);
        $tagPayload = static function (int $major) use ($tags, $rawTags): string {
            if ($rawTags !== null) {
                return $rawTags;
            }

            $entries = array_values(array_filter(
                $tags,
                static fn (string $tag): bool => preg_match('/^v?'.preg_quote((string) $major, '/').'\\./', $tag) === 1,
            ));

            return json_encode(
                array_map(static fn (string $tag): array => ['ref' => 'refs/tags/'.$tag], $entries),
                JSON_THROW_ON_ERROR,
            );
        };

        $map = [
            'https://raw.githubusercontent.com/laravel/docs/11.x/upgrade.md' => $sources['laravel-11'] ?? $this->guide(11),
            'https://raw.githubusercontent.com/laravel/docs/12.x/upgrade.md' => $sources['laravel-12'] ?? $this->guide(12),
            'https://raw.githubusercontent.com/laravel/docs/13.x/upgrade.md' => $sources['laravel-13'] ?? $this->guide(13),
            GuideDriftChecker::CARBON_URL => $sources['carbon-3'] ?? $this->carbon(),
            GuideDriftChecker::TAGS_URL.'10.' => $tagPayload(10),
            GuideDriftChecker::TAGS_URL.'11.' => $tagPayload(11),
            GuideDriftChecker::TAGS_URL.'12.' => $tagPayload(12),
            GuideDriftChecker::TAGS_URL.'13.' => $tagPayload(13),
            GuideDriftChecker::TAGS_URL.'14.' => $tagPayload(14),
        ];

        $fetcher = new class($map) implements SourceFetcher
        {
            /** @param array<string, string> $map */
            public function __construct(private readonly array $map) {}

            public function fetch(string $url, int $maxBytes): string
            {
                if (! isset($this->map[$url])) {
                    throw new RuntimeException('Unknown fixture URL.');
                }

                $contents = $this->map[$url];

                if (strlen($contents) > $maxBytes) {
                    throw new RuntimeException(sprintf('Source "%s" exceeds the %d-byte limit.', $url, $maxBytes));
                }

                return $contents;
            }
        };

        return (new GuideDriftChecker($this->root, $fetcher))->run();
    }

    private function guide(int $major): string
    {
        return sprintf("# Laravel %d Upgrade\n\n## Breaking changes\n", $major);
    }

    private function carbon(): string
    {
        return "# Carbon 3 migration\n\n## Migration changes\n";
    }

    private function makeFixtureDirectory(string $guideHeading = 'Breaking changes', string $carbonHeading = 'Migration changes'): string
    {
        $directory = sys_get_temp_dir().'/guide-drift-fixture-'.bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        foreach ([11, 12, 13] as $major) {
            file_put_contents($directory.'/upgrade-'.$major.'.md', "# Laravel $major Upgrade\n\n## $guideHeading\n");
        }

        file_put_contents($directory.'/carbon-3.md', "# Carbon 3 migration\n\n## $carbonHeading\n");
        file_put_contents($directory.'/laravel-tags.json', json_encode([
            ['ref' => 'refs/tags/v10.3.3'],
            ['ref' => 'refs/tags/v11.6.1'],
            ['ref' => 'refs/tags/v12.12.2'],
            ['ref' => 'refs/tags/v13.10.1'],
        ], JSON_THROW_ON_ERROR));

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            if (is_dir($path) && ! is_link($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
