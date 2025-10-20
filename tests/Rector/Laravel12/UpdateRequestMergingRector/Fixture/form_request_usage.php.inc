<?php

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