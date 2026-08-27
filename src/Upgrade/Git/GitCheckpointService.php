<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Git;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\BinaryResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use RuntimeException;
use Throwable;

/**
 * Captures git safety state and creates narrowly scoped upgrade checkpoints.
 *
 * The baseline is captured before the first step changes the project. Existing
 * dirty paths are never staged, even when allowDirty is enabled.
 */
final class GitCheckpointService
{
    private bool $prepared = false;

    private bool $active = false;

    private string $projectRoot = '';

    private string $repositoryRoot = '';

    private string $baselineFile = '';

    /** @var array<string, true> */
    private array $baselinePaths = [];

    /** @var list<string> */
    private array $pendingCodePaths = [];

    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly ?BinaryResolver $binaryResolver = null,
    ) {}

    public function prepare(UpgradeContext $context): GitCheckpointResult
    {
        if (! $this->isEnabled($context)) {
            $this->prepared = true;

            return GitCheckpointResult::skipped(
                $context->isPlanMode() ? 'Plan mode: git safety and commits were not run.' : 'Git safety disabled by noGit.',
                ['reason' => $context->isPlanMode() ? 'plan-mode' : 'noGit'],
            );
        }

        if ($this->prepared) {
            return $this->active
                ? GitCheckpointResult::successful('Git safety baseline already captured.')
                : GitCheckpointResult::skipped('Git repository is unavailable.', ['reason' => 'git-unavailable']);
        }

        $this->prepared = true;
        $project = realpath($context->workingDirectory);

        if ($project === false) {
            return GitCheckpointResult::failed('The git safety working directory does not exist.', ['check' => 'working-directory']);
        }

        $git = $this->binaryResolver()->gitBinary();

        try {
            $rootResult = $this->processRunner->run(new ProcessRequest(
                [$git, 'rev-parse', '--show-toplevel'],
                $context->workingDirectory,
            ));
        } catch (Throwable $exception) {
            return $this->unavailable('Git could not be launched: '.$exception->getMessage());
        }

        if (! $rootResult->isSuccessful()) {
            return $this->unavailable('The working directory is not a git repository.', $this->processData($rootResult));
        }

        $repository = realpath(trim($rootResult->combinedOutput()));

        if ($repository === false || ! $this->withinProject($project, $repository)) {
            return $this->unavailable('The project is not safely contained by its git repository.');
        }

        try {
            $statusResult = $this->processRunner->run(new ProcessRequest(
                [$git, 'status', '--porcelain=v1', '-z', '--untracked-files=all'],
                str_replace('\\', '/', $repository),
            ));
        } catch (Throwable $exception) {
            return $this->unavailable('Git status could not be read: '.$exception->getMessage());
        }

        if (! $statusResult->isSuccessful()) {
            return $this->unavailable('Git status could not be read.', $this->processData($statusResult));
        }

        $this->projectRoot = str_replace('\\', '/', $project);
        $this->repositoryRoot = str_replace('\\', '/', $repository);
        $this->baselineFile = $this->projectRoot.'/.laravel-upgrade/git-baseline.json';
        $storedBaseline = $this->loadBaseline($context);
        $this->baselinePaths = $storedBaseline['paths'] ?? $this->statusPaths($statusResult->combinedOutput());
        $this->pendingCodePaths = $storedBaseline['pending'] ?? [];
        $this->active = true;

        if ($storedBaseline === null) {
            $this->persistBaseline($context);
        }

        return GitCheckpointResult::successful(
            'Git safety baseline captured.',
            ['repository' => $this->repositoryRoot, 'baselinePaths' => array_keys($this->baselinePaths)],
        );
    }

    public function afterStep(string $step, UpgradeContext $context, StepResult $result): GitCheckpointResult
    {
        $prepared = $this->prepare($context);

        if (! $prepared->isSuccessful() && ! $prepared->isSkipped()) {
            return $prepared;
        }

        foreach ($result->changedFiles as $changedFile) {
            $safe = $this->projectRelativePath($changedFile);

            if ($safe !== null && ! in_array($safe, $this->pendingCodePaths, true)) {
                $this->pendingCodePaths[] = $safe;
            }
        }

        if ($this->active) {
            $this->persistBaseline($context);
        }

        if (! $this->active || ! in_array($step, ['dependencies', 'install', 'skeleton', 'post'], true)) {
            return $prepared->isSkipped()
                ? $prepared
                : GitCheckpointResult::successful('No git checkpoint is required for this step.', ['step' => $step]);
        }

        $paths = $result->changedFiles;
        $message = sprintf('chore(upgrade): sync Laravel %d skeleton', $context->toMajor());

        if ($step === 'dependencies') {
            $paths = ['composer.json'];
            $message = sprintf('chore(upgrade): propose Laravel %d dependencies', $context->toMajor());
        } elseif ($step === 'install') {
            $paths = ['composer.lock'];
            $message = sprintf('chore(upgrade): install Laravel %d dependencies', $context->toMajor());
        } elseif ($step === 'post') {
            $paths = array_merge($this->pendingCodePaths, $result->changedFiles, $this->newMigrationPaths($context));
            $message = sprintf('refactor(upgrade): apply Laravel %d code changes', $context->toMajor());
        }

        $checkpoint = $this->commitPaths($context, $paths, $message);

        if ($checkpoint->isSuccessful()) {
            if ($step === 'post') {
                $this->pendingCodePaths = [];
                $this->persistBaseline($context);
            }

            return $checkpoint;
        }

        return $checkpoint;
    }

    public function finalize(UpgradeContext $context): GitCheckpointResult
    {
        $prepared = $this->prepare($context);

        if (! $prepared->isSuccessful() && ! $prepared->isSkipped()) {
            return $prepared;
        }

        if (! $this->active) {
            return $prepared->isSkipped()
                ? $prepared
                : GitCheckpointResult::skipped('No git repository is available for the final commit.', ['reason' => 'git-unavailable']);
        }

        $reportPath = $this->projectRoot.'/UPGRADE-REPORT.md';

        if (! is_file($reportPath)) {
            return GitCheckpointResult::skipped(
                'The final upgrade report does not exist; report generation remains deferred.',
                ['reason' => 'report-not-found', 'path' => $reportPath],
            );
        }

        $ignorePath = $this->repositoryRelativePath('.gitignore');
        $ignoreWasDirty = isset($this->baselinePaths[$ignorePath]);

        if (! $ignoreWasDirty) {
            try {
                $this->ensureGitignore();
            } catch (Throwable $exception) {
                return GitCheckpointResult::failed('Could not update .gitignore: '.$exception->getMessage(), ['check' => 'gitignore']);
            }
        }

        $result = $this->commitPaths(
            $context,
            ['UPGRADE-REPORT.md', '.gitignore'],
            sprintf('docs(upgrade): add Laravel %d upgrade report', $context->toMajor()),
        );

        if ($result->isSuccessful() || $result->isSkipped()) {
            $this->clearBaseline();
        }

        if ($ignoreWasDirty) {
            $result = new GitCheckpointResult(
                $result->status,
                $result->message,
                $result->data + ['gitignore' => 'Pre-existing dirty .gitignore was left unchanged; add the upgrade metadata ignore entry manually.'],
                $result->exitCode,
            );
        }

        return $result;
    }

    /** @param list<string> $paths */
    private function commitPaths(UpgradeContext $context, array $paths, string $message): GitCheckpointResult
    {
        $current = $this->currentStatus($context);

        if ($current === null) {
            return GitCheckpointResult::failed('Git status could not be read before checkpointing.', ['check' => 'status']);
        }

        $safePaths = [];

        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $projectPath = $this->projectRelativePath($path);

            if ($projectPath === null || str_starts_with($projectPath, '.laravel-upgrade/')) {
                continue;
            }

            // Environment files contain secrets and are never an upgrade
            // checkpoint target, even if a caller accidentally reports one.
            if ($projectPath === '.env') {
                continue;
            }

            $repositoryPath = $this->repositoryRelativePath($projectPath);

            if (isset($this->baselinePaths[$repositoryPath])) {
                continue;
            }

            if (isset($current[$repositoryPath])) {
                $safePaths[$repositoryPath] = true;
            }
        }

        if ($safePaths === []) {
            return GitCheckpointResult::skipped(
                sprintf('No new files require the %s checkpoint.', $message),
                ['message' => $message, 'staged' => []],
            );
        }

        $git = $this->binaryResolver()->gitBinary();
        $pathsToStage = array_keys($safePaths);

        try {
            $add = $this->processRunner->run(new ProcessRequest(
                array_merge([$git, 'add', '--'], $pathsToStage),
                $this->gitWorkingDirectory($context),
            ));
        } catch (Throwable $exception) {
            return GitCheckpointResult::failed(
                'Git could not stage the checkpoint files: '.$exception->getMessage(),
                ['check' => 'git-add', 'paths' => $pathsToStage],
            );
        }

        if (! $add->isSuccessful()) {
            return GitCheckpointResult::failed(
                'Git could not stage the checkpoint files.',
                ['check' => 'git-add', 'paths' => $pathsToStage, 'process' => $this->processData($add)],
            );
        }

        try {
            $commit = $this->processRunner->run(new ProcessRequest(
                array_merge([$git, 'commit', '--only', '-m', $message, '--'], $pathsToStage),
                $this->gitWorkingDirectory($context),
            ));
        } catch (Throwable $exception) {
            return GitCheckpointResult::failed(
                'Git could not create the checkpoint commit: '.$exception->getMessage(),
                ['check' => 'git-commit', 'paths' => $pathsToStage],
            );
        }

        if (! $commit->isSuccessful()) {
            return GitCheckpointResult::failed(
                'Git could not create the checkpoint commit.',
                ['check' => 'git-commit', 'paths' => $pathsToStage, 'process' => $this->processData($commit)],
            );
        }

        return GitCheckpointResult::successful(
            sprintf('Created checkpoint commit: %s', $message),
            ['message' => $message, 'staged' => $pathsToStage, 'process' => $this->processData($commit)],
        );
    }

    /** @return array<string, true>|null */
    private function currentStatus(UpgradeContext $context): ?array
    {
        $git = $this->binaryResolver()->gitBinary();

        try {
            $result = $this->processRunner->run(new ProcessRequest(
                [$git, 'status', '--porcelain=v1', '-z', '--untracked-files=all'],
                $this->gitWorkingDirectory($context),
            ));
        } catch (Throwable) {
            return null;
        }

        return $result->isSuccessful() ? $this->statusPaths($result->combinedOutput()) : null;
    }

    /** @return list<string> */
    private function newMigrationPaths(UpgradeContext $context): array
    {
        $status = $this->currentStatus($context) ?? [];
        $paths = [];

        foreach (array_keys($status) as $repositoryPath) {
            $projectPath = $this->projectPathFromRepositoryPath($repositoryPath);

            if ($projectPath !== null && str_starts_with($projectPath, 'database/migrations/')) {
                $paths[] = $projectPath;
            }
        }

        return $paths;
    }

    /** @return array<string, true> */
    private function statusPaths(string $output): array
    {
        $paths = [];

        $records = explode("\0", $output);

        for ($index = 0, $count = count($records); $index < $count; $index++) {
            $record = $records[$index];

            if (strlen($record) < 4 || $record[2] !== ' ') {
                continue;
            }

            $path = substr($record, 3);

            if ($path !== '') {
                $paths[$path] = true;
            }

            // Porcelain v1 -z emits the new path as a second NUL-delimited
            // record for renames/copies. Include it in the safety set too.
            if (($record[0] === 'R' || $record[0] === 'C') && isset($records[$index + 1])) {
                $renamedPath = $records[++$index];

                if ($renamedPath !== '') {
                    $paths[$renamedPath] = true;
                }
            }
        }

        return $paths;
    }

    /** @return array{paths: array<string, true>, pending: list<string>}|null */
    private function loadBaseline(UpgradeContext $context): ?array
    {
        if (! is_file($this->baselineFile)) {
            return null;
        }

        $contents = file_get_contents($this->baselineFile);

        if ($contents === false) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($decoded)
            || ($decoded['runId'] ?? null) !== $context->runId
            || ($decoded['repository'] ?? null) !== $this->repositoryRoot
            || ! is_array($decoded['paths'] ?? null)) {
            return null;
        }

        $paths = [];

        foreach ($decoded['paths'] as $path) {
            if (! is_string($path) || $path === '') {
                return null;
            }

            $paths[$path] = true;
        }

        $rawPending = $decoded['pending'] ?? [];

        if (! is_array($rawPending)) {
            return null;
        }

        $pending = [];

        foreach ($rawPending as $path) {
            if (! is_string($path)) {
                return null;
            }

            $safePath = $this->projectRelativePath($path);

            if ($safePath === null) {
                return null;
            }

            $pending[] = $safePath;
        }

        return ['paths' => $paths, 'pending' => array_values(array_unique($pending))];
    }

    private function persistBaseline(UpgradeContext $context): void
    {
        $directory = dirname($this->baselineFile);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            return;
        }

        $contents = json_encode([
            'runId' => $context->runId,
            'repository' => $this->repositoryRoot,
            'paths' => array_keys($this->baselinePaths),
            'pending' => $this->pendingCodePaths,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
        $temporaryPath = tempnam($directory, 'baseline-');

        if ($temporaryPath === false) {
            return;
        }

        try {
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === strlen($contents)) {
                rename($temporaryPath, $this->baselineFile);
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function clearBaseline(): void
    {
        if ($this->baselineFile !== '' && is_file($this->baselineFile)) {
            unlink($this->baselineFile);
        }
    }

    private function ensureGitignore(): void
    {
        $path = $this->projectRoot.'/.gitignore';

        if (is_link($path)) {
            $target = realpath($path);

            if ($target === false || ! $this->withinProject($target, $this->projectRoot)) {
                throw new RuntimeException('.gitignore is a symlink outside the project.');
            }
        }

        $contents = is_file($path) ? file_get_contents($path) : '';

        if ($contents === false) {
            throw new RuntimeException('Could not read .gitignore.');
        }

        if (preg_match('/(?m)^\s*\/?\.laravel-upgrade\/?\s*$/', $contents) === 1) {
            return;
        }

        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $suffix = $contents === '' || str_ends_with($contents, "\n") || str_ends_with($contents, "\r") ? '' : $eol;
        $updated = $contents.$suffix.'.laravel-upgrade/'.$eol;

        if (file_put_contents($path, $updated, LOCK_EX) !== strlen($updated)) {
            throw new RuntimeException('Could not write .gitignore.');
        }
    }

    private function isEnabled(UpgradeContext $context): bool
    {
        return ! $context->isPlanMode()
            && $context->option('noGit', false) !== true
            && $context->option('git', true) !== false;
    }

    /** @param array<string, mixed> $data */
    private function unavailable(string $message, array $data = []): GitCheckpointResult
    {
        $this->active = false;

        return GitCheckpointResult::skipped($message, $data + ['reason' => 'git-unavailable']);
    }

    private function projectRelativePath(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        if (str_starts_with($path, $this->projectRoot.'/')) {
            $path = substr($path, strlen($this->projectRoot) + 1);
        } elseif (str_starts_with($path, '/')) {
            return null;
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        if ($path === '.laravel-upgrade' || str_starts_with($path, '.laravel-upgrade/')) {
            return null;
        }

        return $path;
    }

    private function repositoryRelativePath(string $projectPath): string
    {
        $prefix = $this->repositoryProjectPrefix();

        return $prefix === '' ? $projectPath : $prefix.'/'.$projectPath;
    }

    private function projectPathFromRepositoryPath(string $repositoryPath): ?string
    {
        $prefix = $this->repositoryProjectPrefix();

        if ($prefix === '') {
            return $repositoryPath;
        }

        return str_starts_with($repositoryPath, $prefix.'/')
            ? substr($repositoryPath, strlen($prefix) + 1)
            : null;
    }

    private function repositoryProjectPrefix(): string
    {
        if ($this->repositoryRoot === $this->projectRoot) {
            return '';
        }

        return ltrim(substr($this->projectRoot, strlen($this->repositoryRoot)), '/');
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

    private function gitWorkingDirectory(UpgradeContext $context): string
    {
        return $this->repositoryRoot !== '' ? $this->repositoryRoot : $context->workingDirectory;
    }

    /** @return array<string, mixed> */
    private function processData(ProcessResult $result): array
    {
        return [
            'command' => $result->arguments,
            'exitCode' => $result->exitCode,
            'output' => $result->combinedOutput(),
        ];
    }
}
