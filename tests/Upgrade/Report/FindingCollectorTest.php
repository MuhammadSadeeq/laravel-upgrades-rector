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

    public function test_round_trip_through_array(): void
    {
        $collector = new FindingCollector;
        $original = $collector->add('r', 'high', 13, 'config/session.php', 42, 'serialization changed');
        $restored = Finding::fromArray($original->toArray());

        self::assertSame($original->file, $restored->file);
        self::assertSame($original->line, $restored->line);
    }
}
