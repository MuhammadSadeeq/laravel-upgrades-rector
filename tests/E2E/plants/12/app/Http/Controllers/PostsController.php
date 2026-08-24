<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class PostsController extends Controller
{
    public function index(LengthAwarePaginator $posts): View
    {
        $posts->links('pagination::default');

        return view('posts.index', ['posts' => $posts]);
    }
}
