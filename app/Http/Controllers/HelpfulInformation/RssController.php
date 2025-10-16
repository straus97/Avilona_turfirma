<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

class RssController extends Controller
{
    public function __invoke()
    {
        $rss = Cache::remember('news.rss', 60, function () {
            $xmlString = file_get_contents('https://www.turizm.ru/news/rss/yandex/');
            return $xmlString;
        });
        return Response::make($rss, 200, [
            'Content-Type' => 'application/xml'
        ]);
    }
}
