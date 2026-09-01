<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AvatarRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'avatar' => 'image',
            // A field named image is not an image validation rule.
            'image' => 'nullable|string',
        ];
    }
}
