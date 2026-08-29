<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Application;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ComposerCli;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Exercises the deps command end to end. The --dry-run test must prove that
 * nothing at all is written; the other tests prove Composer's own
 * JsonManipulator keeps indentation, key order and the trailing newline.
 *
 * The solver part of the command (`composer update --dry-run -W`) depends on
 * network state and on Composer >= 2.10's advisory policy, so the full-run
 * test tolerates its failure (exit code 3) while asserting the exact
 * composer.json mutations.
 */
final class DepsCommandIntegrationTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $composerBinary = trim((string) shell_exec('command -v composer'));

        if ($composerBinary === '') {
            self::markTestSkipped('No composer binary available on PATH.');
        }

        $this->workspace = sys_get_temp_dir().'/laravel-upgrades-deps-'.uniqid('', true);

        if (! mkdir($this->workspace, 0777, true) && ! is_dir($this->workspace)) {
            self::fail('Could not create the integration workspace.');
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->workspace)) {
            $this->recursiveDelete($this->workspace);
        }
    }

    public function test_dry_run_leaves_the_tree_byte_identical(): void
    {
        $this->writeComposerJson($this->sampleManifest());

        $before = $this->treeChecksums();

        $exitCode = $this->runDeps(11, true);

        self::assertSame(0, $exitCode, 'The dry run should succeed.');
        self::assertSame($before, $this->treeChecksums(), 'A dry run must not touch a single file.');
    }

    public function test_apply_preserves_formatting_and_only_changes_intended_lines(): void
    {
        $this->writeComposerJson($this->sampleManifest());

        [$exitCode] = $this->runDepsCaptured(11, false);

        // Exit 0 = solver agreed; exit 3 = solver disagreed with the proposal
        // (network or advisory policy). Both mean the manifest was edited.
        self::assertContains($exitCode, [0, 3], 'The apply run must not crash.');

        $contents = (string) file_get_contents($this->workspace.'/composer.json');

        // Indentation and trailing newline preserved.
        self::assertStringContainsString('    "name": "acme/app",', $contents);
        self::assertStringEndsWith("\n}\n", $contents);

        // Decisions applied.
        self::assertStringContainsString('"laravel/framework": "^11.0.0"', $contents);
        self::assertStringContainsString('"php": "^8.2', $contents);
        self::assertStringNotContainsString('doctrine/dbal', $contents);
        self::assertStringNotContainsString('spatie/once', $contents);

        // Untouched entries keep their position and constraint.
        self::assertMatchesRegularExpression(
            '/"guzzlehttp\/guzzle": "\^7\.2"/',
            $contents,
            'Unrelated packages must stay untouched.'
        );

        // The empty allow-plugins object is preserved as an object ({}), never [].
        self::assertStringContainsString('"allow-plugins": {}', $contents);

        self::assertStringNotContainsString('"require": []', $contents);
    }

    public function test_current_ecosystem_major_does_not_print_a_spurious_package_guide(): void
    {
        $this->writeComposerJson(<<<'JSON'
{
    "require": {
        "livewire/livewire": "^3.0"
    }
}
JSON
        );
        self::assertNotFalse(file_put_contents($this->workspace.'/composer.lock', json_encode([
            'packages' => [['name' => 'livewire/livewire', 'version' => 'v3.7.0']],
            'packages-dev' => [],
        ], JSON_THROW_ON_ERROR)));

        [$exitCode, $output] = $this->runDepsCaptured(11, true);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('All dependencies already admit the target major.', $output);
        self::assertStringNotContainsString('Package upgrade guides', $output);
    }

    public function test_dry_run_renders_package_guide_actions_not_only_counts_and_urls(): void
    {
        $this->writeComposerJson(<<<'JSON'
{
    "require": {
        "laravel/passport": "^11.0"
    }
}
JSON
        );
        self::assertNotFalse(file_put_contents($this->workspace.'/composer.lock', json_encode([
            'packages' => [['name' => 'laravel/passport', 'version' => 'v11.0.0']],
            'packages-dev' => [],
        ], JSON_THROW_ON_ERROR)));

        [$exitCode, $output] = $this->runDepsCaptured(13, true);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Package upgrade guides', $output);
        self::assertStringContainsString('Remove Passport::routes()', $output);
        self::assertStringContainsString('Call Passport::enablePasswordGrant()', $output);
    }

    public function test_require_and_remove_through_composer_keep_formatting_byte_for_byte(): void
    {
        $manifest = <<<'JSON'
{
    "name": "acme/app",
    "type": "project",
      "require": {
          "php": "^8.1",
          "laravel/framework": "^10.10"
      }
}
JSON;
        $this->writeComposerJson($manifest);

        $cli = new ComposerCli($this->workspace);
        $cli->removePackages(['laravel/framework'], false);
        $cli->requirePackages(['php' => '^8.2.0'], false);
        $cli->requirePackages(['pestphp/pest' => '^2.0.0'], true);

        // Existing lines keep their (odd) indentation byte for byte; the new
        // require-dev section is inserted at Composer's canonical 4-space level.
        $expected = <<<'JSON'
{
    "name": "acme/app",
    "type": "project",
      "require": {
          "php": "^8.2.0"
      },
    "require-dev": {
        "pestphp/pest": "^2.0.0"
    }
}
JSON;

        self::assertSame($expected, rtrim((string) file_get_contents($this->workspace.'/composer.json')));
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runDepsCaptured(int $targetMajor, bool $dryRun): array
    {
        $application = new Application;
        $application->setAutoExit(false);

        $arguments = [
            'command' => 'deps',
            'target-major' => (string) $targetMajor,
            '--working-dir' => $this->workspace,
        ];

        if ($dryRun) {
            $arguments['--dry-run'] = true;
        }

        $output = new BufferedOutput;
        $exitCode = $application->run(new ArrayInput($arguments), $output);

        return [$exitCode, $output->fetch()];
    }

    private function runDeps(int $targetMajor, bool $dryRun): int
    {
        return $this->runDepsCaptured($targetMajor, $dryRun)[0];
    }

    /**
     * @return array<string, string>
     */
    private function treeChecksums(): array
    {
        $checksums = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->workspace, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (! $fileInfo->isFile()) {
                continue;
            }

            $relative = str_replace($this->workspace.'/', '', $fileInfo->getPathname());
            $checksums[$relative] = md5_file($fileInfo->getPathname()) ?: '';
        }

        ksort($checksums);

        return $checksums;
    }

    private function writeComposerJson(string $contents): void
    {
        self::assertNotFalse(file_put_contents($this->workspace.'/composer.json', $contents));
    }

    private function sampleManifest(): string
    {
        return <<<'JSON'
{
    "name": "acme/app",
    "type": "project",
    "description": "Fixture application.",
    "license": "MIT",
    "require": {
        "php": "^8.1",
        "laravel/framework": "^10.10",
        "doctrine/dbal": "^3.0",
        "guzzlehttp/guzzle": "^7.2"
    },
    "require-dev": {
        "spatie/once": "^3.0"
    },
    "config": {
        "allow-plugins": {}
    }
}
JSON;
    }

    private function recursiveDelete(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isDir() && ! $fileInfo->isLink()) {
                @rmdir($fileInfo->getPathname());

                continue;
            }

            @unlink($fileInfo->getPathname());
        }

        @rmdir($directory);
    }
}
