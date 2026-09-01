<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Project;

use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Project\ProjectDependency;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Project\ProjectInspection;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Project\ProjectInspector;
use PHPUnit\Framework\TestCase;

final class ProjectInspectorTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir().'/project-inspector-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    public function test_inspect_reports_authoritative_packages_configuration_and_tooling(): void
    {
        $directory = dirname(__DIR__).'/fixtures/project-app';
        $manifest = $this->manifest($directory);
        $inspector = new ProjectInspector($directory, $manifest);
        $inspection = $inspector->inspect();

        self::assertSame(10, $inspection->laravelMajor);
        self::assertSame(48, $inspection->laravelMinor);
        self::assertSame('v10.48.29', $inspection->laravelVersion);
        self::assertSame('vendor/composer/installed.json', $inspection->laravelVersionSource);
        self::assertNull($inspection->laravelVersionWarning);
        self::assertSame(ProjectInspection::TYPE_APP, $inspection->projectType);
        self::assertSame(['sqlite', 'mysql'], $inspection->databaseDrivers);
        self::assertSame('mysql', $inspection->databaseDefault);
        self::assertSame('database', $inspection->queueDefault);
        self::assertSame('database', $inspection->sessionDriver);
        self::assertSame('json', $inspection->sessionSerialization);
        self::assertTrue($inspection->pintPresent);
        self::assertTrue($inspection->larastanPresent);
        self::assertFalse($inspection->gitRepository);
        self::assertFalse($inspection->gitClean);

        self::assertSame([
            ['php', 'require', '^8.2', null, null],
            ['laravel/framework', 'require', '^10.10', '10.48.29', 'installed'],
            ['guzzlehttp/guzzle', 'require', '^7.0', '7.8.1', 'installed'],
            ['laravel/pint', 'require-dev', '^1.0', '1.18.0', 'installed'],
            ['larastan/larastan', 'require-dev', '^2.0', '2.9.2', 'installed'],
        ], array_map(
            static fn (ProjectDependency $dependency): array => [
                $dependency->name,
                $dependency->section,
                $dependency->constraint,
                $dependency->installedVersion,
                $dependency->installedSource,
            ],
            $inspector->installedDirectPackages(),
        ));
    }

    public function test_malformed_installed_metadata_falls_back_to_lock_then_manifest(): void
    {
        $this->writeProjectFiles([
            'composer.json' => json_encode([
                'require' => [
                    'php' => '^8.1',
                    'laravel/framework' => '^10.10',
                    'example/package' => '^1.0',
                ],
            ], JSON_THROW_ON_ERROR),
            'vendor/composer/installed.json' => '{malformed',
            'composer.lock' => json_encode([
                'packages' => [
                    ['name' => 'laravel/framework', 'version' => 'v10.22.4'],
                    ['name' => 'example/package', 'pretty_version' => '1.2.0'],
                ],
                'packages-dev' => [],
            ], JSON_THROW_ON_ERROR),
        ]);

        $manifest = $this->manifest($this->temporaryDirectory);
        $inspector = new ProjectInspector($this->temporaryDirectory, $manifest);
        $detection = $inspector->laravelVersion();

        self::assertSame(10, $detection->major);
        self::assertSame(22, $detection->minor());
        self::assertSame('v10.22.4', $detection->version);
        self::assertSame('composer.lock', $detection->source);
        self::assertNotNull($detection->warning);
        self::assertSame('1.2.0', $inspector->directDependencies()[2]->installedVersion);
        self::assertSame('lock', $inspector->directDependencies()[2]->installedSource);

        unlink($this->temporaryDirectory.'/composer.lock');
        $manifestOnly = new ProjectInspector($this->temporaryDirectory, $manifest);
        $fallback = $manifestOnly->laravelVersion();

        self::assertSame(10, $fallback->major);
        self::assertSame(10, $fallback->minor());
        self::assertSame('composer.json', $fallback->source);
        self::assertNotNull($fallback->warning);
        self::assertNull($manifestOnly->directDependencies()[1]->installedVersion);
        self::assertNull($manifestOnly->directDependencies()[1]->installedSource);
    }

    public function test_missing_or_malformed_config_is_read_only_and_library_is_classified(): void
    {
        $directory = dirname(__DIR__).'/fixtures/project-library';
        $inspection = (new ProjectInspector($directory, $this->manifest($directory)))->inspect();

        self::assertSame(ProjectInspection::TYPE_LIBRARY, $inspection->projectType);
        self::assertSame(10, $inspection->laravelMajor);
        self::assertSame(0, $inspection->laravelMinor);
        self::assertSame('composer.json', $inspection->laravelVersionSource);
        self::assertNotNull($inspection->laravelVersionWarning);
        self::assertSame([], $inspection->databaseDrivers);
        self::assertNull($inspection->databaseDefault);
        self::assertNull($inspection->queueDefault);
        self::assertNull($inspection->sessionDriver);
        self::assertNull($inspection->sessionSerialization);
        self::assertFalse($inspection->pintPresent);
        self::assertFalse($inspection->larastanPresent);
        self::assertNull((new ProjectInspector($directory, $this->manifest($directory)))->directDependencies()[0]->installedVersion);
    }

    public function test_lock_only_tooling_is_not_reported_as_installed(): void
    {
        $this->writeProjectFiles([
            'composer.json' => json_encode([
                'require' => ['laravel/framework' => '^10.0'],
                'require-dev' => [
                    'laravel/pint' => '^1.0',
                    'larastan/larastan' => '^2.0',
                ],
            ], JSON_THROW_ON_ERROR),
            'composer.lock' => json_encode([
                'packages' => [
                    ['name' => 'laravel/framework', 'version' => 'v10.48.0'],
                ],
                'packages-dev' => [
                    ['name' => 'laravel/pint', 'version' => 'v1.18.0'],
                    ['name' => 'larastan/larastan', 'version' => 'v2.9.2'],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $inspection = (new ProjectInspector(
            $this->temporaryDirectory,
            $this->manifest($this->temporaryDirectory),
        ))->inspect();

        self::assertFalse($inspection->pintPresent);
        self::assertFalse($inspection->larastanPresent);
        self::assertSame('lock', $inspection->directDependencies[1]->installedSource);
        self::assertSame('lock', $inspection->directDependencies[2]->installedSource);
    }

    public function test_database_default_is_reported_as_a_driver_without_connections(): void
    {
        $this->writeProjectFiles([
            'composer.json' => json_encode([
                'require' => ['laravel/framework' => '^10.0'],
            ], JSON_THROW_ON_ERROR),
            'config/database.php' => "<?php\n\nreturn ['default' => 'sqlite'];\n",
        ]);

        $inspection = (new ProjectInspector(
            $this->temporaryDirectory,
            $this->manifest($this->temporaryDirectory),
        ))->inspect();

        self::assertSame(['sqlite'], $inspection->databaseDrivers);
        self::assertSame('sqlite', $inspection->databaseDefault);
    }

    public function test_process_failures_are_converted_to_safe_unknown_facts(): void
    {
        $this->writeProjectFiles([
            '.git' => 'gitdir: /missing',
        ]);
        $runner = new FailingProjectProcessRunner;
        $inspector = new ProjectInspector($this->temporaryDirectory, [], processRunner: $runner);

        self::assertSame('?', $inspector->composerVersion());
        self::assertFalse($inspector->isGitClean());
        self::assertSame('', $inspector->gitBranch());
        self::assertCount(3, $runner->requests);

        foreach ($runner->requests as $request) {
            self::assertSame(5.0, $request->timeout);
        }
    }

    public function test_invalid_process_working_directory_does_not_escape_inspection(): void
    {
        $inspector = new ProjectInspector($this->temporaryDirectory.'/missing', []);

        self::assertSame('?', $inspector->composerVersion());
        self::assertFalse($inspector->isGitClean());
        self::assertSame('', $inspector->gitBranch());
    }

    /** @return array<string, mixed> */
    private function manifest(string $directory): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode((string) file_get_contents($directory.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            self::fail($exception->getMessage());
        }

        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** @param array<string, string> $files */
    private function writeProjectFiles(array $files): void
    {
        foreach ($files as $relative => $contents) {
            $path = $this->temporaryDirectory.'/'.$relative;
            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($path, $contents);
        }
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}

final class FailingProjectProcessRunner implements ProcessRunner
{
    /** @var list<ProcessRequest> */
    public array $requests = [];

    public function run(ProcessRequest $request): ProcessResult
    {
        $this->requests[] = $request;

        throw new \RuntimeException('process failed');
    }
}
