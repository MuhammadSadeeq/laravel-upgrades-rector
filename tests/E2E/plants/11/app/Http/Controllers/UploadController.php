<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Psr\Log\LoggerInterface as Logger;

final class UploadController
{
    public function __construct(
        private ?Logger $l = null,
        private string $bucket = 'default',
    ) {}

    /**
     * Laravel 12's image rule no longer accepts SVG implicitly. The field
     * named image is intentionally unrelated and must not be reported.
     */
    public function store(Request $request): array
    {
        $request->mergeIfMissing([
            'meta' => ['a' => 1],
        ]);

        $request->mergeIfMissing([
            'meta.author' => 'auto',
            'status' => 'pending',
        ]);

        $validated = $request->validate([
            'avatar' => 'required|image',
            'image' => 'nullable|string',
        ]);

        Validator::make($request->all(), ['avatar' => 'image']);

        Storage::disk('local')->put('avatars/user.jpg', 'contents');

        return $validated;
    }

    /** Laravel 12 now passes the schema connection as Blueprint argument zero. */
    public function legacyBlueprint(): Blueprint
    {
        return new Blueprint('posts', static function (Blueprint $table): void {});
    }

    /** A connection-first Blueprint is already compatible with Laravel 12. */
    public function connectionAwareBlueprint(Connection $connection): Blueprint
    {
        return new Blueprint($connection, 'posts', static function (Blueprint $table): void {});
    }

    /** Laravel 12 schema inspection now includes every schema by default. */
    public function schemaTables(): array
    {
        return Schema::getTables();
    }

    /** A typed Carbon receiver is handled by the Carbon 3 transform set. */
    public function daysSince(Carbon $start): int|float
    {
        return $start->diffInDays(Carbon::now());
    }
}
