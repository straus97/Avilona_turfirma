<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Support\Facades\Response;

class RssController extends Controller
{
    public function __invoke()
    {
        // Локальная лента: читаем только собственные новости.
        // SoftDeletes у модели News исключает удалённые записи автоматически.
        $items = News::query()
            ->orderByDesc('pub_date')
            ->orderByDesc('id')
            ->get(['title', 'link', 'description', 'pub_date']);

        $xml = view('news.rss', ['items' => $items])->render();

        return Response::make($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
