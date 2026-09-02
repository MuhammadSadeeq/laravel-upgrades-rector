<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

final class StrUuidTest extends TestCase
{
    /** A normal UUID read is not a custom factory mutation. */
    public function test_uuid_generation_is_safe(): void
    {
        self::assertNotSame('', (string) Str::uuid());
    }
}
