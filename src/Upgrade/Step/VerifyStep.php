<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\BinaryResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use RuntimeException;
use Throwable;

/** Performs the read-only and executable checks that close an upgrade step. */
final class VerifyStep implements StepInterface
{
    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly ?BinaryResolver $binaryResolver = null,
    ) {}

    public function name(): string
    {
        return 'verify';
    }

    public function execute(UpgradeContext $context): StepResult
    {
        $project = realpath($context->workingDirectory);

        if ($project === false) {
            return StepResult::failed(
                message: 'The verification working directory does not exist.',
                data: ['check' => 'working-directory'],
                exitCode: 1,
            );
        }

        $checks = [];
        $collector = new FindingCollector;
        $changed = $this->changedFiles($context, $project);

        if ($changed['invalid'] !== []) {
            foreach ($changed['invalid'] as $path) {
                $this->finding(
                    $collector,
                    $context,
                    'laravelUpgrade.verify.changed-file',
                    sprintf('Changed file path is outside the project or contains traversal segments: %s.', $path),
                    'Only project-contained changed files can be verified.',
                    'verification',
                );
            }

            return $this->finish($context, $collector, $checks, $changed['files'], [], 'changed-file-containment');
        }

        $composer = $this->composerBinary($context);
        $this->runCommand(
            $context,
            $collector,
            $checks,
            'composer-validate',
            [$composer, 'validate', '--strict'],
            'composer.json',
        );

        foreach ($changed['files'] as $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
                $checks[] = [
                    'id' => 'lint:'.($this->relativePath($file, $project) ?? basename($file)),
                    'status' => 'skipped',
                    'reason' => 'not-php',
                    'file' => $this->relativePath($file, $project) ?? basename($file),
                ];

                continue;
            }

            $this->runCommand(
                $context,
                $collector,
                $checks,
                'lint:'.($this->relativePath($file, $project) ?? basename($file)),
                [$this->binaryResolver()->phpBinary(), '-l', $file],
                $this->relativePath($file, $project) ?? 'changed file',
            );
        }

        $invalidClasses = $this->invalidConfiguredClasses($context);
        $classes = $this->changedClasses($context, $changed['files']);
        $autoload = $this->projectFile($project, 'vendor/autoload.php');

        if ($invalidClasses !== []) {
            foreach ($invalidClasses as $class) {
                $this->finding(
                    $collector,
                    $context,
                    'laravelUpgrade.verify.changed-class',
                    sprintf('Configured changed class name is invalid: %s.', $class),
                    'Use a fully qualified PHP class, interface, trait, or enum name.',
                    'verification',
                );
            }

            $checks[] = [
                'id' => 'class-load',
                'status' => 'skipped',
                'reason' => 'invalid-changed-class',
                'classes' => $classes,
            ];
        } elseif ($classes === []) {
            $checks[] = ['id' => 'class-load', 'status' => 'skipped', 'reason' => 'no-changed-classes'];
        } elseif ($autoload === null) {
            $checks[] = [
                'id' => 'class-load',
                'status' => 'skipped',
                'reason' => 'autoload-not-found',
                'classes' => $classes,
            ];

            if (! $context->isPlanMode()) {
                $this->finding(
                    $collector,
                    $context,
                    'laravelUpgrade.verify.class-load',
                    'Changed classes could not be loaded because vendor/autoload.php is missing.',
                    'Install dependencies before running verification.',
                    'vendor/autoload.php',
                );
            }
        } else {
            $script = $this->classLoadScript($autoload, $classes);
            $this->runCommand(
                $context,
                $collector,
                $checks,
                'class-load',
                [$this->binaryResolver()->phpBinary(), '-r', $script],
                'changed classes',
            );
        }

        $artisan = $this->projectFile($project, 'artisan');
        $isLibrary = $this->isLibrary($context);

        if ($artisan === null && ! $isLibrary) {
            $this->finding(
                $collector,
                $context,
                'laravelUpgrade.verify.artisan',
                'The Laravel application entry point artisan is missing.',
                'Restore artisan or mark this project as a library when verification is not applicable.',
                'artisan',
            );
        }

        if ($artisan !== null) {
            $this->verifyArtisan(
                $context,
                $collector,
                $checks,
                $artisan,
            );
        } else {
            $reason = $isLibrary ? 'library-project' : 'artisan-not-found';
            foreach (['about', 'routes', 'config-cache', 'config-clear', 'tests'] as $id) {
                $checks[] = ['id' => $id, 'status' => 'skipped', 'reason' => $reason];
            }
        }

        $this->verifyPhpStan($context, $collector, $checks, $project);

        return $this->finish($context, $collector, $checks, $changed['files'], $classes, 'verification');
    }

    /**
     * @param  list<array<string, mixed>>  &$checks
     * @param  list<string>  $changedFiles
     * @param  list<string>  $classes
     */
    private function finish(
        UpgradeContext $context,
        FindingCollector $collector,
        array $checks,
        array $changedFiles,
        array $classes,
        string $check,
    ): StepResult {
        $findings = array_map(
            static fn (Finding $finding): array => $finding->toArray(),
            $collector->all(),
        );
        $data = [
            'check' => $check,
            'checks' => $checks,
            'changedFiles' => $changedFiles,
            'changedClasses' => $classes,
            'findings' => $findings,
        ];

        if ($context->isPlanMode()) {
            $data['findingPersistence'] = 'skipped-plan-mode';

            return $collector->count() === 0
                ? StepResult::successful(
                    message: 'Verification checks previewed; no commands were run.',
                    data: $data,
                )
                : StepResult::failed(
                    message: sprintf('Verification preview found %d issue(s).', $collector->count()),
                    findingsCount: $collector->count(),
                    data: $data,
                    exitCode: 1,
                );
        }

        try {
            $findingsPath = $this->persistFindings($context, $collector);
            $data['findingsJsonl'] = $findingsPath;
            $data['findingPersistence'] = 'applied';
        } catch (Throwable $exception) {
            $data['persistenceError'] = $exception->getMessage();

            return StepResult::failed(
                message: 'Verification findings could not be persisted: '.$exception->getMessage(),
                findingsCount: $collector->count(),
                data: $data,
                exitCode: 1,
            );
        }

        if ($collector->count() !== 0) {
            return StepResult::failed(
                message: sprintf('Verification found %d issue(s).', $collector->count()),
                findingsCount: $collector->count(),
                data: $data,
                exitCode: 1,
            );
        }

        return StepResult::successful(
            message: 'Verification checks passed.',
            data: $data,
        );
    }

    /**
     * @param  list<array<string, mixed>>  &$checks
     */
    private function verifyArtisan(
        UpgradeContext $context,
        FindingCollector $collector,
        array &$checks,
        string $artisan,
    ): void {
        $php = $this->binaryResolver()->phpBinary();
        $about = $this->runCommand(
            $context,
            $collector,
            $checks,
            'about',
            [$php, 'artisan', 'about', '--json'],
            'artisan',
        );

        if ($about !== null && $about->isSuccessful() && $this->parseJson($about->combinedOutput()) === null) {
            $this->finding(
                $collector,
                $context,
                'laravelUpgrade.verify.about-json',
                'The artisan about boot check did not return valid JSON.',
                'Fix boot output or application errors before continuing.',
                'artisan',
            );
        }

        $routes = null;

        if ($context->toMajor() >= 12) {
            $routeResult = $this->runCommand(
                $context,
                $collector,
                $checks,
                'routes',
                [$php, 'artisan', 'route:list', '--json'],
                'routes',
            );

            if ($routeResult !== null && $routeResult->isSuccessful()) {
                $routes = $this->parseRoutes($routeResult->combinedOutput());

                if ($routes === null) {
                    $this->finding(
                        $collector,
                        $context,
                        'laravelUpgrade.verify.routes-json',
                        'The artisan route list did not return valid JSON.',
                        'Fix route registration output before continuing.',
                        'routes',
                    );
                } else {
                    $this->findDuplicateRoutes($context, $collector, $routes);

                    if ($context->toMajor() >= 13) {
                        $this->findDomainPrecedenceConflicts($context, $collector, $routes);
                    }
                }
            }
        } else {
            $checks[] = ['id' => 'routes', 'status' => 'skipped', 'reason' => 'target-before-laravel-12'];
        }

        $configCache = $this->runCommand(
            $context,
            $collector,
            $checks,
            'config-cache',
            [$php, 'artisan', 'config:cache'],
            'config',
        );

        // Clearing is deliberately independent of cache success: a partial or
        // stale cache must not be left behind after a failed cache attempt.
        $this->runCommand(
            $context,
            $collector,
            $checks,
            'config-clear',
            [$php, 'artisan', 'config:clear'],
            'config',
        );

        if ($context->option('noTests', false) === true) {
            $checks[] = ['id' => 'tests', 'status' => 'skipped', 'reason' => 'noTests'];
        } else {
            $tests = $this->runCommand(
                $context,
                $collector,
                $checks,
                'tests',
                [$php, 'artisan', 'test'],
                'tests',
            );

            if ($tests !== null) {
                $summary = $this->testSummary($tests->combinedOutput());

                if ($summary !== null) {
                    $checks[] = ['id' => 'tests-summary', 'status' => 'parsed', 'summary' => $summary];
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  &$checks
     */
    private function verifyPhpStan(
        UpgradeContext $context,
        FindingCollector $collector,
        array &$checks,
        string $project,
    ): void {
        if ($context->option('verifyPhpstan', false) !== true) {
            $checks[] = ['id' => 'phpstan', 'status' => 'skipped', 'reason' => 'disabled'];

            return;
        }

        $config = null;

        foreach (['phpstan.neon', 'phpstan.neon.dist'] as $candidate) {
            $config = $this->projectFile($project, $candidate);

            if ($config !== null) {
                break;
            }
        }

        if ($config === null) {
            $checks[] = ['id' => 'phpstan', 'status' => 'skipped', 'reason' => 'project-config-not-found'];

            return;
        }

        $configured = $context->option('phpstanBinary');
        $binary = is_string($configured) && $configured !== ''
            ? $this->projectFile($project, $configured)
            : $this->projectFile($project, 'vendor/bin/phpstan');

        if ($binary === null) {
            $this->finding(
                $collector,
                $context,
                'laravelUpgrade.verify.phpstan-binary',
                'The optional PHPStan regression check was requested but the project-local binary is missing or unsafe.',
                'Install PHPStan in the project or correct phpstanBinary.',
                'vendor/bin/phpstan',
            );

            $checks[] = ['id' => 'phpstan', 'status' => 'failed', 'reason' => 'binary-not-found'];

            return;
        }

        $this->runCommand(
            $context,
            $collector,
            $checks,
            'phpstan',
            [$binary, 'analyse', '-c', $config, '--no-progress'],
            'phpstan.neon',
        );
    }

    /**
     * @param  list<array<string, mixed>>  &$checks
     * @param  list<string>  $command
     */
    private function runCommand(
        UpgradeContext $context,
        FindingCollector $collector,
        array &$checks,
        string $id,
        array $command,
        string $file,
    ): ?ProcessResult {
        $record = ['id' => $id, 'command' => $command];

        if ($context->isPlanMode()) {
            $checks[] = $record + ['status' => 'preview'];

            return null;
        }

        try {
            $result = $this->processRunner->run(new ProcessRequest($command, $context->workingDirectory, 1800.0));
        } catch (Throwable $exception) {
            $checks[] = $record + ['status' => 'failed', 'launchError' => $exception->getMessage()];
            $this->finding(
                $collector,
                $context,
                'laravelUpgrade.verify.'.$id,
                sprintf('Verification command "%s" could not be launched: %s', $id, $exception->getMessage()),
                'Resolve the command or environment error and run verification again.',
                $file,
            );

            return null;
        }

        $checks[] = $record + [
            'status' => $result->isSuccessful() ? 'success' : 'failed',
            'exitCode' => $result->exitCode,
            'output' => $result->combinedOutput(),
        ];

        if (! $result->isSuccessful()) {
            $this->finding(
                $collector,
                $context,
                'laravelUpgrade.verify.'.$id,
                sprintf('Verification command "%s" failed with exit code %d.', $id, $result->exitCode),
                $result->combinedOutput() !== ''
                    ? 'Review the command output: '.$result->combinedOutput()
                    : 'Fix the verification failure and run the command again.',
                $file,
            );
        }

        return $result;
    }

    /**
     * @return array{files: list<string>, invalid: list<string>}
     */
    private function changedFiles(UpgradeContext $context, string $project): array
    {
        $values = [];
        $sources = [
            $context->option('changedFiles'),
            $context->option('currentChangedFiles'),
            $context->option('journal'),
            $context->option('state'),
        ];

        foreach ($sources as $source) {
            $this->collectChangedFileValues($source, $values, false);
        }

        $files = [];
        $invalid = [];

        foreach (array_values(array_unique($values)) as $value) {
            $resolved = $this->projectFile($project, $value);

            if ($resolved === null) {
                $invalid[] = $value;

                continue;
            }

            $files[] = $resolved;
        }

        sort($files);
        sort($invalid);

        return ['files' => $files, 'invalid' => $invalid];
    }

    /**
     * @param  list<string>  &$values
     */
    private function collectChangedFileValues(mixed $source, array &$values, bool $nested): void
    {
        if (is_string($source)) {
            if (! $nested || $source !== '') {
                $values[] = $source;
            }

            return;
        }

        if (! is_array($source)) {
            return;
        }

        foreach ($source as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), ['changedfiles', 'changed_files'], true)) {
                $this->collectChangedFileValues($value, $values, true);

                continue;
            }

            if ($nested && is_array($value)) {
                $this->collectChangedFileValues($value, $values, true);
            }
        }

        if (! $nested && array_is_list($source)) {
            foreach ($source as $value) {
                if (is_string($value)) {
                    $values[] = $value;
                }
            }
        }
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    private function changedClasses(UpgradeContext $context, array $files): array
    {
        $classes = [];
        $configured = $context->option('changedClasses');

        if (is_array($configured)) {
            foreach ($configured as $class) {
                if (is_string($class) && preg_match('/^\\?[A-Za-z_][A-Za-z0-9_]*(?:\\[A-Za-z_][A-Za-z0-9_]*)*$/', $class) === 1) {
                    $classes[] = ltrim($class, '\\');
                }
            }
        }

        foreach ($files as $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
                continue;
            }

            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            $namespace = '';
            $tokens = token_get_all($contents);
            $tokenCount = count($tokens);

            for ($index = 0; $index < $tokenCount; $index++) {
                $token = $tokens[$index];

                if (! is_array($token)) {
                    continue;
                }

                if ($token[0] === T_NAMESPACE) {
                    [$namespace, $index] = $this->parseNamespace($tokens, $index + 1);

                    continue;
                }

                if (! in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)
                    || $this->previousSignificantToken($tokens, $index) === T_NEW) {
                    continue;
                }

                $nameToken = $this->nextSignificantToken($tokens, $index + 1);

                if (! is_array($nameToken) || ! in_array($nameToken[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }

                $name = ltrim($nameToken[1], '\\');
                $classes[] = $namespace !== '' ? $namespace.'\\'.$name : $name;
            }
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }

    /** @return list<string> */
    private function invalidConfiguredClasses(UpgradeContext $context): array
    {
        $configured = $context->option('changedClasses');

        if ($configured === null) {
            return [];
        }

        if (! is_array($configured)) {
            return ['(changedClasses must be a list)'];
        }

        $invalid = [];

        foreach ($configured as $class) {
            if (is_string($class) && preg_match('/^\\?[A-Za-z_][A-Za-z0-9_]*(?:\\[A-Za-z_][A-Za-z0-9_]*)*$/', $class) === 1) {
                continue;
            }

            $invalid[] = is_string($class) && $class !== '' ? $class : get_debug_type($class);
        }

        return $invalid;
    }

    /**
     * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens
     * @return array{0: string, 1: int}
     */
    private function parseNamespace(array $tokens, int $start): array
    {
        $parts = [];
        $index = $start;
        $count = count($tokens);

        for (; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($token === ';' || $token === '{') {
                return [ltrim(implode('', $parts), '\\'), $index];
            }

            if (! is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) || $token[1] === '\\') {
                $parts[] = $token[1];
            }
        }

        return [ltrim(implode('', $parts), '\\'), $index];
    }

    /**
     * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function previousSignificantToken(array $tokens, int $index): ?int
    {
        for ($index--; $index >= 0; $index--) {
            $token = $tokens[$index];

            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                return $token[0];
            }

            if (trim($token) !== '') {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens
     * @return array{0: int, 1: string, 2: int}|string|null
     */
    private function nextSignificantToken(array $tokens, int $start): array|string|null
    {
        $count = count($tokens);

        for ($index = $start; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (is_array($token)) {
                return $token;
            }

            if (trim($token) !== '') {
                return $token;
            }
        }

        return null;
    }

    /** @param list<string> $classes */
    private function classLoadScript(string $autoload, array $classes): string
    {
        return '$autoload = '.var_export($autoload, true).'; '
            .'$classes = '.var_export($classes, true).'; '
            .'require $autoload; '
            .'$missing = []; '
            .'foreach ($classes as $class) { '
            .'if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) { '
            .'$missing[] = $class; '
            .'} '
            .'} '
            .'if ($missing !== []) { fwrite(STDERR, json_encode(["missing" => $missing])); exit(1); }';
    }

    /** @param list<array<string, mixed>> $routes */
    private function findDuplicateRoutes(UpgradeContext $context, FindingCollector $collector, array $routes): void
    {
        $byName = [];

        foreach ($routes as $route) {
            $name = is_string($route['name'] ?? null) ? trim($route['name']) : '';

            if ($name !== '') {
                $byName[$name] = ($byName[$name] ?? 0) + 1;
            }
        }

        foreach ($byName as $name => $count) {
            if ($count < 2) {
                continue;
            }

            $this->finding(
                $collector,
                $context,
                'laravelUpgrade.verify.duplicate-route-name',
                sprintf('Duplicate route name "%s" appears %d times.', $name, $count),
                'Give each route a unique name; Laravel 12 resolves duplicate names to the first registered route.',
                'routes',
            );
        }
    }

    /** @param list<array<string, mixed>> $routes */
    private function findDomainPrecedenceConflicts(UpgradeContext $context, FindingCollector $collector, array $routes): void
    {
        $count = count($routes);

        for ($left = 0; $left < $count; $left++) {
            $first = $routes[$left];
            $firstUri = is_string($first['uri'] ?? null) ? trim($first['uri']) : '';
            $firstMethod = $this->routeMethod($first['method'] ?? null);
            $firstDomain = is_string($first['domain'] ?? null) ? trim($first['domain']) : '';

            if ($firstUri === '' || $firstMethod === '') {
                continue;
            }

            for ($right = $left + 1; $right < $count; $right++) {
                $second = $routes[$right];
                $secondUri = is_string($second['uri'] ?? null) ? trim($second['uri']) : '';
                $secondMethod = $this->routeMethod($second['method'] ?? null);
                $secondDomain = is_string($second['domain'] ?? null) ? trim($second['domain']) : '';

                if ($firstUri !== $secondUri || $firstMethod !== $secondMethod
                    || ($firstDomain === '') === ($secondDomain === '')) {
                    continue;
                }

                $this->finding(
                    $collector,
                    $context,
                    'laravelUpgrade.verify.domain-route-precedence',
                    sprintf('Domain and non-domain routes share %s %s and may change precedence.', $firstMethod, $firstUri),
                    'Make the route domains and order explicit; Laravel 13 changed domain route precedence.',
                    'routes',
                );
            }
        }
    }

    private function routeMethod(mixed $method): string
    {
        if (is_array($method)) {
            $method = implode('|', array_filter($method, 'is_string'));
        }

        return is_string($method) ? strtoupper(trim($method)) : '';
    }

    /** @return list<array<string, mixed>>|null */
    private function parseRoutes(string $output): ?array
    {
        $decoded = $this->parseJson($output);

        if ($decoded === null) {
            return null;
        }

        $routes = $decoded['routes'] ?? $decoded;

        if (! is_array($routes)) {
            return null;
        }

        /** @var list<array<string, mixed>> $result */
        $result = [];

        foreach ($routes as $route) {
            if (is_array($route)) {
                /** @var array<string, mixed> $normalized */
                $normalized = [];

                foreach ($route as $key => $value) {
                    if (is_string($key)) {
                        $normalized[$key] = $value;
                    }
                }

                $result[] = $normalized;
            }
        }

        return $result;
    }

    /** @return array<int|string, mixed>|null */
    private function parseJson(string $output): ?array
    {
        $trimmed = trim($output);

        if ($trimmed === '') {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $objectStart = strpos($trimmed, '{');
            $objectEnd = strrpos($trimmed, '}');
            $listStart = strpos($trimmed, '[');
            $listEnd = strrpos($trimmed, ']');

            $useList = $listStart !== false && ($objectStart === false || $listStart < $objectStart);
            $start = $useList ? $listStart : $objectStart;
            $end = $useList ? $listEnd : $objectEnd;

            if ($start === false || $end === false || $end < $start) {
                return null;
            }

            try {
                $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }
        }

        if (! is_array($decoded)) {
            return null;
        }

        $result = [];

        foreach ($decoded as $key => $value) {
            if (is_int($key)) {
                $result[$key] = $value;

                continue;
            }

            $result[(string) $key] = $value;
        }

        return $result;
    }

    /** @return array<string, int|string>|null */
    private function testSummary(string $output): ?array
    {
        $testsCount = null;
        $assertionsCount = null;
        $failuresCount = null;
        $errorsCount = null;
        $skippedCount = null;
        $status = null;

        if (preg_match('/\bOK\s*\(\s*(\d+)\s+tests?,\s*(\d+)\s+assertions?\s*\)/i', $output, $match) === 1) {
            $testsCount = (int) $match[1];
            $assertionsCount = (int) $match[2];
            $status = 'passed';
        }

        if (preg_match('/\bTests\s*:?[\s]+(\d+)/i', $output, $match) === 1) {
            $testsCount = (int) $match[1];
        }

        if (preg_match('/\bAssertions:\s*(\d+)/i', $output, $match) === 1) {
            $assertionsCount = (int) $match[1];
        }

        if ($assertionsCount === null && preg_match('/\(\s*(\d+)\s+assertions?\s*\)/i', $output, $match) === 1) {
            $assertionsCount = (int) $match[1];
        }

        if (preg_match('/\bFailures:\s*(\d+)/i', $output, $match) === 1) {
            $failuresCount = (int) $match[1];
        }

        if (preg_match('/\bErrors?:\s*(\d+)/i', $output, $match) === 1) {
            $errorsCount = (int) $match[1];
        }

        if (preg_match('/\bSkipped:\s*(\d+)/i', $output, $match) === 1) {
            $skippedCount = (int) $match[1];
        }

        if ($status === null && preg_match('/\b(\d+)\s+(?:tests?\s+)?passed\b/i', $output) === 1) {
            $status = 'passed';
        }

        if ($status === null && preg_match('/\b(\d+)\s+(?:tests?\s+)?failed\b/i', $output) === 1) {
            $status = 'failed';
        }

        if ($failuresCount !== null && $failuresCount > 0 || $errorsCount !== null && $errorsCount > 0) {
            $status = 'failed';
        }

        if ($testsCount === null) {
            return null;
        }

        $summary = [
            'status' => $status ?? 'unknown',
            'tests' => $testsCount,
        ];

        if ($assertionsCount !== null) {
            $summary['assertions'] = $assertionsCount;
        }

        if ($failuresCount !== null) {
            $summary['failures'] = $failuresCount;
        }

        if ($errorsCount !== null) {
            $summary['errors'] = $errorsCount;
        }

        if ($skippedCount !== null) {
            $summary['skipped'] = $skippedCount;
        }

        return $summary;
    }

    private function composerBinary(UpgradeContext $context): string
    {
        $configured = $context->option('composerBinary');

        return $this->binaryResolver()->composerBinary(is_string($configured) && $configured !== '' ? $configured : null);
    }

    private function isLibrary(UpgradeContext $context): bool
    {
        if ($context->option('isLibrary', false) === true || $context->option('library', false) === true) {
            return true;
        }

        foreach (['projectType', 'type'] as $key) {
            $value = $context->option($key);

            if (is_string($value) && strtolower($value) === 'library') {
                return true;
            }
        }

        return false;
    }

    private function projectFile(string $project, string $path): ?string
    {
        $normalized = str_replace('\\', '/', $path);

        if ($normalized === '' || str_contains($normalized, "\0")) {
            return null;
        }

        $segments = explode('/', $normalized);

        foreach ($segments as $segment) {
            if ($segment === '..' || $segment === '.') {
                return null;
            }
        }

        $isAbsolute = str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1;

        if (! $isAbsolute) {
            if ($segments !== array_values(array_filter($segments, static fn (string $segment): bool => $segment !== ''))) {
                return null;
            }

            $normalized = rtrim(str_replace('\\', '/', $project), '/').'/'.$normalized;
        }

        $candidate = realpath($normalized);

        if ($candidate === false || ! $this->withinProject($candidate, $project) || ! is_file($candidate)) {
            return null;
        }

        return str_replace('\\', '/', $candidate);
    }

    private function relativePath(string $path, string $project): ?string
    {
        $path = str_replace('\\', '/', $path);
        $project = rtrim(str_replace('\\', '/', $project), '/');

        return str_starts_with($path, $project.'/') ? substr($path, strlen($project) + 1) : null;
    }

    private function withinProject(string $candidate, string $project): bool
    {
        $candidate = rtrim(str_replace('\\', '/', $candidate), '/');
        $project = rtrim(str_replace('\\', '/', $project), '/');

        return $candidate === $project || str_starts_with($candidate, $project.'/');
    }

    private function binaryResolver(): BinaryResolver
    {
        return $this->binaryResolver ?? new BinaryResolver;
    }

    private function finding(
        FindingCollector $collector,
        UpgradeContext $context,
        string $ruleId,
        string $message,
        string $action,
        string $file,
    ): void {
        $collector->add(
            ruleId: $ruleId,
            severity: Finding::SEVERITY_HIGH,
            laravelVersion: $context->toMajor(),
            file: $file,
            line: 0,
            message: $message,
            action: $action,
        );
    }

    private function persistFindings(UpgradeContext $context, FindingCollector $collector): string
    {
        $directory = rtrim($context->workingDirectory, '/').'/.laravel-upgrade';

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the .laravel-upgrade directory.');
        }

        $path = $directory.'/findings.jsonl';
        $collector->writeJsonl($path);

        return $path;
    }
}
