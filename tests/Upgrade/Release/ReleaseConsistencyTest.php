<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Release;

use MuhammadSadeeq\LaravelUpgradesRector\PackageInfo;
use MuhammadSadeeq\LaravelUpgradesRector\Release\ReleaseValidator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Application;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\ReportWriter;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\UpgradeReportGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ReleaseConsistencyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function test_cli_and_report_surfaces_use_the_canonical_package_version(): void
    {
        self::assertSame('muhammadsadeeq/laravel-upgrades-rector', PackageInfo::NAME);
        self::assertSame('1.0.0', PackageInfo::VERSION);
        self::assertSame(PackageInfo::VERSION, Application::VERSION);
        self::assertSame(PackageInfo::TOOL, UpgradeReportGenerator::TOOL);

        $path = sys_get_temp_dir().'/laravel-upgrade-release-'.bin2hex(random_bytes(5)).'.json';

        try {
            (new ReportWriter)->writeJson([], [], $path);
            $report = json_decode((string) file_get_contents($path), true);

            self::assertIsArray($report);
            self::assertSame(PackageInfo::TOOL, $report['tool'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_check_release_validates_the_current_changelog_and_metadata(): void
    {
        $process = $this->checker();
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString(PackageInfo::VERSION, $process->getOutput());
    }

    public function test_initial_release_uses_a_github_tag_link(): void
    {
        $directory = $this->repositoryFixture();

        try {
            self::assertSame([], (new ReleaseValidator($directory))->validate());

            $changelogPath = $directory.'/CHANGELOG.md';
            $changelog = (string) file_get_contents($changelogPath);
            $updated = str_replace(
                '[1.0.0]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/releases/tag/v1.0.0',
                '[1.0.0]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v0.9.0...v1.0.0',
                $changelog,
            );
            file_put_contents($changelogPath, $updated);

            $errors = (new ReleaseValidator($directory))->validate();

            self::assertStringContainsString(
                'CHANGELOG.md [1.0.0] link must be "https://github.com/muhammadsadeeq/laravel-upgrades-rector/releases/tag/v1.0.0".',
                implode("\n", $errors),
            );
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function test_future_release_uses_a_compare_link_to_the_previous_release(): void
    {
        $directory = $this->repositoryFixture();

        try {
            $changelogPath = $directory.'/CHANGELOG.md';
            $changelog = (string) file_get_contents($changelogPath);
            $changelog = str_replace(
                '## [1.0.0] — 2026-08-30',
                "## [1.1.0] — 2026-08-31\n\nFuture release.\n\n## [1.0.0] — 2026-08-30",
                $changelog,
            );
            $changelog = str_replace(
                '[Unreleased]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.0.0...HEAD',
                '[Unreleased]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.1.0...HEAD',
                $changelog,
            );
            $changelog .= "\n[1.1.0]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.0.0...v1.1.0\n";
            file_put_contents($changelogPath, $changelog);

            self::assertSame([], (new ReleaseValidator($directory, '1.1.0'))->validate());

            file_put_contents(
                $changelogPath,
                str_replace(
                    '[1.1.0]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.0.0...v1.1.0',
                    '[1.1.0]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/releases/tag/v1.1.0',
                    $changelog,
                ),
            );

            $errors = (new ReleaseValidator($directory, '1.1.0'))->validate();

            self::assertStringContainsString(
                'CHANGELOG.md [1.1.0] link must be "https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.0.0...v1.1.0".',
                implode("\n", $errors),
            );
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function test_checker_accepts_both_root_option_forms_for_an_alternate_repository(): void
    {
        $directory = $this->repositoryFixture();

        try {
            foreach ([['--root', $directory], ['--root='.$directory]] as $arguments) {
                $process = $this->checker($arguments);
                $process->run();

                self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            }
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function test_checker_accepts_both_tag_option_forms_and_rejects_wrong_tag(): void
    {
        foreach ([['--tag', 'v9.9.9'], ['--tag=v9.9.9']] as $arguments) {
            $process = $this->checker($arguments);
            $process->run();

            self::assertSame(1, $process->getExitCode());
            self::assertStringContainsString('Release tag must be', $process->getErrorOutput());
        }
    }

    public function test_checker_reports_missing_values_and_unknown_options_with_usage_error(): void
    {
        foreach (['--root', '--root=', '--tag', '--tag=', '--unknown'] as $argument) {
            $process = $this->checker([$argument]);
            $process->run();

            self::assertSame(2, $process->getExitCode(), $argument);
        }

        $process = $this->checker(['--help']);
        $process->run();

        self::assertSame(0, $process->getExitCode());
        self::assertStringContainsString('Usage: bin/check-release', $process->getOutput());
    }

    public function test_semver_validation_is_strict_but_accepts_prerelease_and_build_metadata(): void
    {
        foreach (['0.0.0', '1.2.3', '1.2.3-rc.1', '1.2.3-rc.1+build.5', '1.2.3+001'] as $version) {
            self::assertTrue(ReleaseValidator::isValidSemver($version), $version);
        }

        foreach (['01.2.3', '1.02.3', '1.2.03', '1.2.3-01', '1.2', '1.2.3-', '1.2.3+'] as $version) {
            self::assertFalse(ReleaseValidator::isValidSemver($version), $version);
        }

        $errors = (new ReleaseValidator($this->root, '01.2.3'))->validate();

        self::assertStringContainsString('not a valid SemVer', implode("\n", $errors));
    }

    public function test_changelog_release_date_must_be_exact_and_calendar_valid(): void
    {
        foreach ([
            'missing' => "## [1.0.0]\n",
            'invalid' => '## [1.0.0] — 2026-02-30',
            'suffix' => '## [1.0.0] — 2026-08-30 trailing',
        ] as $label => $replacement) {
            $directory = $this->repositoryFixture();

            try {
                $changelogPath = $directory.'/CHANGELOG.md';
                $changelog = (string) file_get_contents($changelogPath);
                $updated = str_replace('## [1.0.0] — 2026-08-30', $replacement, $changelog);
                file_put_contents($changelogPath, $updated);

                $errors = (new ReleaseValidator($directory))->validate();

                self::assertNotSame([], $errors, $label);
                self::assertStringContainsString('date', implode("\n", $errors), $label);
            } finally {
                $this->removeDirectory($directory);
            }
        }
    }

    public function test_annotated_tag_at_head_passes_tag_validation(): void
    {
        $directory = $this->repositoryFixture();

        try {
            $this->git($directory, ['init', '-q']);
            $this->git($directory, ['config', 'user.email', 'release-test@example.test']);
            $this->git($directory, ['config', 'user.name', 'Release Test']);
            $this->git($directory, ['add', 'composer.json', 'CHANGELOG.md']);
            $this->git($directory, ['commit', '-qm', 'fixture']);
            $this->git($directory, ['tag', '-a', 'v1.0.0', '-m', 'Release v1.0.0']);

            self::assertSame([], (new ReleaseValidator($directory))->validate());
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function test_lightweight_tag_is_rejected_when_validating_a_release_tag(): void
    {
        $directory = $this->repositoryFixture();

        try {
            $this->git($directory, ['init', '-q']);
            $this->git($directory, ['config', 'user.email', 'release-test@example.test']);
            $this->git($directory, ['config', 'user.name', 'Release Test']);
            $this->git($directory, ['add', 'composer.json', 'CHANGELOG.md']);
            $this->git($directory, ['commit', '-qm', 'fixture']);
            $this->git($directory, ['tag', 'v1.0.0']);

            $errors = (new ReleaseValidator($directory))->validate();

            self::assertNotSame([], $errors);
            self::assertStringContainsString('annotated tag', implode("\n", $errors));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function test_annotated_tag_that_does_not_point_at_head_is_rejected(): void
    {
        $directory = $this->repositoryFixture();

        try {
            $this->git($directory, ['init', '-q']);
            $this->git($directory, ['config', 'user.email', 'release-test@example.test']);
            $this->git($directory, ['config', 'user.name', 'Release Test']);
            $this->git($directory, ['add', 'composer.json', 'CHANGELOG.md']);
            $this->git($directory, ['commit', '-qm', 'release']);
            $this->git($directory, ['tag', '-a', 'v1.0.0', '-m', 'Release v1.0.0']);
            file_put_contents($directory.'/composer.json', (string) file_get_contents($directory.'/composer.json')."\n");
            $this->git($directory, ['add', 'composer.json']);
            $this->git($directory, ['commit', '-qm', 'post-release change']);

            $errors = (new ReleaseValidator($directory))->validate('v1.0.0');

            self::assertStringContainsString('point at HEAD', implode("\n", $errors));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function repositoryFixture(): string
    {
        $directory = sys_get_temp_dir().'/laravel-upgrade-release-fixture-'.bin2hex(random_bytes(5));
        mkdir($directory, 0777, true);
        copy($this->root.'/composer.json', $directory.'/composer.json');
        copy($this->root.'/CHANGELOG.md', $directory.'/CHANGELOG.md');

        return $directory;
    }

    /** @param list<string> $arguments */
    private function checker(array $arguments = []): Process
    {
        return new Process(
            array_merge([PHP_BINARY, $this->root.'/bin/check-release'], $arguments),
            $this->root,
        );
    }

    /** @param list<string> $arguments */
    private function git(string $directory, array $arguments): void
    {
        $process = new Process(array_merge(['git'], $arguments), $directory);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($directory);
    }
}
