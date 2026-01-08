<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Http\Filters\NewsFilter;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HelpfulNewsController extends Controller
{
    public function __invoke(Request $request)
    {
        // Синхронизация RSS (раз в час)
        Cache::remember('news.rss.sync', 3600, function () {
            try {
                $xmlString = file_get_contents('https://www.turizm.ru/news/rss/yandex/');
                $rss = new \DOMDocument();
                $rss->loadXML($xmlString);
                $items = $rss->getElementsByTagName('item');
                
                $newsData = [];
                foreach ($items as $item) {
                    $title = $item->getElementsByTagName('title')->item(0);
                    $link = $item->getElementsByTagName('link')->item(0);
                    $content = $item->getElementsByTagNameNS('http://purl.org/rss/1.0/modules/content/', 'encoded')->item(0);
                    $enclosure = $item->getElementsByTagName('enclosure')->item(0);
                    $pubDate = $item->getElementsByTagName('pubDate')->item(0);
                    $slug = Str::slug($title->nodeValue, '-');
                    
                    $image = $enclosure ? $enclosure->getAttribute('url') : null;
                    if (!$image && $content) {
                        $dom = new \DOMDocument();
                        @$dom->loadHTML($content->nodeValue);
                        $images = $dom->getElementsByTagName('img');
                        if ($images->length > 0) {
                            $image = $images->item(0)->getAttribute('src');
                        }
                    }
                    
                    $description = $content ? str_replace('<p>&nbsp;</p>', '', $content->nodeValue) : null;
                    
                    $newsData[] = [
                        'title' => $title ? $title->nodeValue : null,
                        'link' => $link ? $link->nodeValue : null,
                        'description' => $description,
                        'content' => $description,
                        'image' => $image,
                        'pub_date' => $pubDate ? date('Y-m-d H:i:s', strtotime($pubDate->nodeValue)) : null,
                        'slug' => $slug,
                    ];
                }
                
                // Массовое обновление/создание
                foreach ($newsData as $item) {
                    News::updateOrCreate(['link' => $item['link']], $item);
                }
            } catch (\Exception $e) {
                \Log::error('RSS sync error: ' . $e->getMessage());
            }
            
            return true;
        });
        
        // Получение новостей с фильтрами
        $query = News::select('id', 'title', 'link', 'image', 'description', 'pub_date')
            ->orderBy('pub_date', 'desc');
        
        $newsFilter = new NewsFilter($request->all());
        $newsFilter->apply($query);
        
        $news = $query->paginate(6);
        
        return view('helpful_information.news', compact('news'));
    }
}
