<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Report;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use PHPUnit\Framework\TestCase;

final class FindingCollectorTest extends TestCase
{
    public function test_add_and_count(): void
    {
        $collector = new FindingCollector;
        $collector->add('rule.a', 'high', 11, 'app/x.php', 10, 'msg', 'action');
        $collector->add('rule.b', 'low', 11, 'app/y.php', 20, 'msg');

        self::assertSame(2, $collector->count());
        self::assertCount(1, $collector->bySeverity('high'));
    }

    public function test_sequential_ids(): void
    {
        $collector = new FindingCollector;
        $a = $collector->add('r', 'medium', 12, 'f.php', 1, 'm');

        self::assertSame('f-0001', $a->id);
    }

    public function test_identical_findings_are_deduplicated_in_memory(): void
    {
        $collector = new FindingCollector;
        $first = $collector->add('rule.same', 'info', 11, 'routes/web.php', 0, 'same finding', 'review');
        $second = $collector->add('rule.same', 'info', 11, 'routes/web.php', 0, 'same finding', 'review');

        self::assertSame($first->id, $second->id);
        self::assertCount(1, $collector->all());
    }

    public function test_meaningful_context_keeps_findings_distinct(): void
    {
        $collector = new FindingCollector;
        $arguments = ['rule.same', 'info', 11, 'routes/web.php', 0, 'same finding', 'review', 'https://example.test/guide', false, 'high'];

        $collector->add(...$arguments);
        $collector->add(...array_replace($arguments, [2 => 12]));
        $collector->add(...array_replace($arguments, [1 => 'medium']));
        $collector->add(...array_replace($arguments, [7 => 'https://example.test/other']));
        $collector->add(...array_replace($arguments, [8 => true]));
        $collector->add(...array_replace($arguments, [9 => 'medium']));

        self::assertCount(6, $collector->all());
    }

    public function test_legacy_major_field_resolves_to_the_same_identity(): void
    {
        $finding = (new FindingCollector)->add(
            'rule.same',
            'medium',
            12,
            'app/example.php',
            10,
            'same finding',
            'review',
            'guide',
            false,
            'high',
        );

        self::assertSame($finding->identity(), Finding::identityFromArray([
            'ruleId' => 'rule.same',
            'severity' => 'medium',
            'major' => 12,
            'file' => 'app/example.php',
            'line' => 10,
            'message' => 'same finding',
            'action' => 'review',
            'guideUrl' => 'guide',
            'autoFixed' => false,
            'confidence' => 'high',
        ]));
    }

    public function test_round_trip_through_array(): void
    {
        $collector = new FindingCollector;
        $original = $collector->add('r', 'high', 13, 'config/session.php', 42, 'serialization changed');
        $restored = Finding::fromArray($original->toArray());

        self::assertSame($original->file, $restored->file);
        self::assertSame($original->line, $restored->line);
    }

    public function test_jsonl_writes_append_unique_findings_without_colliding_ids(): void
    {
        $directory = sys_get_temp_dir().'/laravel-upgrade-findings-'.bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $path = $directory.'/findings.jsonl';

        try {
            $first = new FindingCollector;
            $first->add('rule.first', 'medium', 11, 'app/first.php', 10, 'first finding');
            $first->writeJsonl($path);

            $second = new FindingCollector;
            $second->add('rule.second', 'low', 12, 'app/second.php', 20, 'second finding');
            $second->writeJsonl($path);

            $third = new FindingCollector;
            $third->add('rule.first', 'medium', 11, 'app/first.php', 10, 'first finding');
            $third->writeJsonl($path);

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            self::assertCount(2, $lines);
            $decoded = array_map(
                static fn (string $line): mixed => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
                $lines,
            );
            self::assertCount(2, $decoded);
            self::assertSame(['f-0001', 'f-0002'], array_column($decoded, 'id'));
            self::assertSame(['rule.first', 'rule.second'], array_column($decoded, 'ruleId'));
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    public function test_jsonl_preserves_distinct_major_and_metadata_contexts(): void
    {
        $directory = sys_get_temp_dir().'/laravel-upgrade-findings-'.bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $path = $directory.'/findings.jsonl';

        try {
            $first = new FindingCollector;
            $first->add('rule.same', 'info', 11, 'app/example.php', 10, 'same', 'review', 'guide', false, 'high');
            $first->writeJsonl($path);

            $second = new FindingCollector;
            $second->add('rule.same', 'info', 12, 'app/example.php', 10, 'same', 'review', 'guide', false, 'high');
            $second->add('rule.same', 'medium', 11, 'app/example.php', 10, 'same', 'review', 'guide', false, 'high');
            $second->writeJsonl($path);

            $third = new FindingCollector;
            $third->add('rule.same', 'info', 11, 'app/example.php', 10, 'same', 'review', 'guide', false, 'high');
            $third->writeJsonl($path);

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            self::assertCount(3, $lines);
            $decoded = array_map(
                static fn (string $line): mixed => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
                $lines,
            );
            self::assertSame([11, 12, 11], array_column($decoded, 'laravelVersion'));
            self::assertSame(['info', 'info', 'medium'], array_column($decoded, 'severity'));
            self::assertSame(['f-0001', 'f-0002', 'f-0003'], array_column($decoded, 'id'));
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }
}
