<?php

use Illuminate\Support\Facades\Validator;

class ValidationContextsTest
{
    // Should change: rules() method
    public function rules()
    {
        return [
            'photo' => 'required|image',
            'avatar' => ['image', 'max:1024'],
        ];
    }

    // Should change: validate() calls
    public function store(Request $request)
    {
        $request->validate([
            'photo' => 'required|image',
            'banner' => ['image'],
        ]);
    }

    // Should change: Validator::make() calls
    public function custom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'image|required',
            'thumbnail' => ['required', 'image'],
        ]);
    }

    // Should change: validateWithBag() calls
    public function withBag(Request $request)
    {
        $request->validateWithBag('default', [
            'photo' => 'image',
        ]);
    }
}

?>
-----
<?php

use Illuminate\Support\Facades\Validator;

class ValidationContextsTest
{
    // Should change: rules() method
    public function rules()
    {
        return [
            'photo' => 'required|image:allow_svg',
            'avatar' => ['image:allow_svg', 'max:1024'],
        ];
    }

    // Should change: validate() calls
    public function store(Request $request)
    {
        $request->validate([
            'photo' => 'required|image:allow_svg',
            'banner' => ['image:allow_svg'],
        ]);
    }

    // Should change: Validator::make() calls
    public function custom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'image:allow_svg|required',
            'thumbnail' => ['required', 'image:allow_svg'],
        ]);
    }

    // Should change: validateWithBag() calls
    public function withBag(Request $request)
    {
        $request->validateWithBag('default', [
            'photo' => 'image:allow_svg',
        ]);
    }
}

?>
