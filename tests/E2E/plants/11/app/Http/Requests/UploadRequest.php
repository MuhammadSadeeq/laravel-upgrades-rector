<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'photo' => 'required|image',
            'file' => ['required', 'image'],
        ];
    }

    public function persist(): void
    {
        $this->mergeIfMissing([
            'photo.meta' => 'auto',
            'status' => 'pending',
        ]);
    }
}
