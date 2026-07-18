<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Http\Filters\NewsFilter;
use App\Models\News;
use Illuminate\Http\Request;

class HelpfulNewsController extends Controller
{
    public function __invoke(Request $request)
    {
        // Публичная страница новостей — только чтение из таблицы news.
        // Синхронизация RSS вынесена в отдельную artisan-команду news:sync-rss
        // (см. App\Services\News\RssNewsSyncService), чтобы GET-запрос не выполнял
        // внешних сетевых обращений и не писал в базу.
        $query = News::select('id', 'title', 'slug', 'link', 'image', 'description', 'pub_date')
            ->orderBy('pub_date', 'desc');
        
        $newsFilter = new NewsFilter($request->all());
        $newsFilter->apply($query);
        
        $news = $query->paginate(6);
        
        return view('helpful_information.news', compact('news'));
    }
}
