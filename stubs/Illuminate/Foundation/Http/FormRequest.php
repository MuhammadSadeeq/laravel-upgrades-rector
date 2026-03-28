<?php

namespace Illuminate\Foundation\Http;

use Illuminate\Http\Request;

class FormRequest extends Request
{
    public function authorize(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function prepareForValidation(): void {}
}
