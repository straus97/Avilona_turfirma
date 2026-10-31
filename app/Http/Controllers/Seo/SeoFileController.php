<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * NOV-PUBLIC-01: отдаёт robots.txt и sitemap.xml из корня репозитория.
 *
 * Корневые файлы остаются единственным источником (их проверяют
 * PublicRobotsConsistencyTest / PublicSitemapConsistencyTest). Корень проекта
 * вне document root (public/), поэтому без этих маршрутов поисковые роботы
 * получили бы 404. Если веб-сервер позже начнёт отдавать public/robots.txt,
 * статический файл просто возьмёт верх.
 */
class SeoFileController extends Controller
{
    public function robots(): Response
    {
        return $this->file('robots.txt', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        return $this->file('sitemap.xml', 'application/xml; charset=UTF-8');
    }

    private function file(string $name, string $contentType): Response
    {
        $path = base_path($name);

        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
