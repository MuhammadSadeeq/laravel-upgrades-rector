<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\ThreeWayMerger;
use PHPUnit\Framework\TestCase;

final class ThreeWayMergerTest extends TestCase
{
    public function test_unchanged_side_takes_the_other_side(): void
    {
        $merger = new ThreeWayMerger;

        self::assertSame('target', $merger->merge('base', 'base', 'target'));
        self::assertSame('custom', $merger->merge('custom', 'base', 'base'));
    }

    public function test_non_overlapping_edits_merge_without_conflict(): void
    {
        $result = (new ThreeWayMerger)->mergeWithStatus(
            "ours\nbase\nthree\n",
            "one\nbase\nthree\n",
            "one\nbase\ntheirs\n"
        );

        self::assertFalse($result['conflicted']);
        self::assertStringContainsString('ours', $result['content']);
        self::assertStringContainsString('theirs', $result['content']);
    }

    public function test_overlapping_edits_contain_conflict_markers(): void
    {
        $result = (new ThreeWayMerger)->mergeWithStatus("one\nours\n", "one\nbase\n", "one\ntheirs\n");

        self::assertTrue($result['conflicted']);
        self::assertStringContainsString('<<<<<<<', $result['content']);
        self::assertStringContainsString('ours', $result['content']);
        self::assertStringContainsString('theirs', $result['content']);
        self::assertNotSame("one\ntheirs\n", $result['content']);
    }

    public function test_laravel_12_bootstrap_customization_merges_with_laravel_13_exception_changes(): void
    {
        $root = dirname(__DIR__, 3);
        $base = file_get_contents($root.'/resources/skeletons/12/bootstrap/app.php');
        $ours = file_get_contents($root.'/tests/E2E/plants/12/bootstrap/app.php');
        $theirs = file_get_contents($root.'/resources/skeletons/13/bootstrap/app.php');

        self::assertIsString($base);
        self::assertIsString($ours);
        self::assertIsString($theirs);

        $result = (new ThreeWayMerger)->mergeWithStatus($ours, $base, $theirs);

        self::assertFalse($result['conflicted']);
        self::assertStringContainsString('validateCsrfTokens', $result['content']);
        self::assertStringContainsString('shouldRenderJsonWhen', $result['content']);
        self::assertStringContainsString("'webhook/*'", $result['content']);
        self::assertStringNotContainsString('<<<<<<<', $result['content']);
    }
}
