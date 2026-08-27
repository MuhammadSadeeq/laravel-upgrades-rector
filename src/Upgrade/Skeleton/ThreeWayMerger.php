<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use Symfony\Component\Process\Process;

/**
 * Small wrapper around git's battle-tested three-way text merge.
 *
 * The method accepts contents rather than paths, which keeps the service easy
 * to test and prevents temporary merge files from leaking into a project.
 */
final class ThreeWayMerger
{
    /**
     * @return array{content: string, conflicted: bool}
     */
    public function mergeWithStatus(string $ours, string $base, string $theirs): array
    {
        if ($ours === $base) {
            return ['content' => $theirs, 'conflicted' => false];
        }

        if ($theirs === $base || $ours === $theirs) {
            return ['content' => $ours, 'conflicted' => false];
        }

        $directory = sys_get_temp_dir().'/laravel-upgrade-merge-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return $this->fallback($ours, $base, $theirs);
        }

        $oursPath = $directory.'/ours';
        $basePath = $directory.'/base';
        $theirsPath = $directory.'/theirs';
        file_put_contents($oursPath, $ours);
        file_put_contents($basePath, $base);
        file_put_contents($theirsPath, $theirs);

        $process = new Process(['git', 'merge-file', '-p', $oursPath, $basePath, $theirsPath]);
        $process->setTimeout(30);
        $process->run();
        $content = $process->getOutput();
        $exitCode = $process->getExitCode();

        @unlink($oursPath);
        @unlink($basePath);
        @unlink($theirsPath);
        @rmdir($directory);

        if ($exitCode === 0 || $exitCode === 1) {
            return ['content' => $content, 'conflicted' => $exitCode === 1];
        }

        return $this->fallback($ours, $base, $theirs);
    }

    public function merge(string $ours, string $base, string $theirs): string
    {
        return $this->mergeWithStatus($ours, $base, $theirs)['content'];
    }

    /**
     * @return array{content: string, conflicted: bool}
     */
    private function fallback(string $ours, string $base, string $theirs): array
    {
        if ($ours === $base) {
            return ['content' => $theirs, 'conflicted' => false];
        }

        if ($theirs === $base || $ours === $theirs) {
            return ['content' => $ours, 'conflicted' => false];
        }

        return [
            'content' => "<<<<<<< ours\n".$ours."=======\n".$theirs.
                ">>>>>>> theirs\n",
            'conflicted' => true,
        ];
    }
}
