<?php

namespace App\Fixtures\Laravel12\UpdateRequestMergingRector;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function prepareForValidation()
    {
        $this->mergeIfMissing([
            'slug' => Str::slug($this->input('name')),
            'is_active' => true,
        ]);
    }

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string',
            'slug' => 'required|string|unique:users',
        ];
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateRequestMergingRector;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function prepareForValidation()
    {
        // @laravel-upgrade request-merge-dots mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging.
        $this->mergeIfMissing([
            'slug' => Str::slug($this->input('name')),
            'is_active' => true,
        ]);
    }

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string',
            'slug' => 'required|string|unique:users',
        ];
    }
}

?>
