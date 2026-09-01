<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\E2E;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\E2E\DiffPayloadNormalizer;
use PHPUnit\Framework\TestCase;

final class DiffPayloadNormalizerTest extends TestCase
{
    public function test_whitespace_only_payloads_are_removed_without_hiding_content_whitespace(): void
    {
        $diff = "diff --git a/file b/file  \r\n"
            ."@@ -1,3 +1,3 @@   \r\n"
            ."-removed  \r\n"
            ."+added\t\r\n"
            ."+    \r\n"
            ."- \r\n"
            ." \t\r\n"
            ."context  \r\n";

        self::assertSame(
            "diff --git a/file b/file  \n"
                ."@@ -1,3 +1,3 @@   \n"
                ."-removed  \n"
                ."+added\t\n"
                ."+\n"
                ."-\n"
                ."\n"
                ."context  \n",
            DiffPayloadNormalizer::normalize($diff),
        );
    }
}
