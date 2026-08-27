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
}
