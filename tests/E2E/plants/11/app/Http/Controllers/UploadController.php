<?php

namespace App\Http\Controllers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

final class UploadController
{
    /**
     * Laravel 12's image rule no longer accepts SVG implicitly. The field
     * named image is intentionally unrelated and must not be reported.
     */
    public function store(Request $request): array
    {
        $validated = $request->validate([
            'avatar' => 'required|image',
            'image' => 'nullable|string',
        ]);

        Validator::make($validated, ['avatar' => 'image']);

        return $validated;
    }

    /** Laravel 12 now passes the schema connection as Blueprint argument zero. */
    public function legacyBlueprint(): Blueprint
    {
        return new Blueprint('posts', static function (Blueprint $table): void {});
    }

    /** Laravel 12 schema inspection now includes every schema by default. */
    public function schemaTables(): array
    {
        return Schema::getTables();
    }
}
