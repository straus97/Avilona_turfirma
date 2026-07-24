<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Models\Article;

class ArticlesController extends Controller
{
    public function __invoke()
    {
        $interesting_news = Article::orderBy('id', 'desc')->paginate(6); // показать 6 записи, начиная с последних
        return view('helpful_information.interesting_articles', compact('interesting_news'));
    }
}
