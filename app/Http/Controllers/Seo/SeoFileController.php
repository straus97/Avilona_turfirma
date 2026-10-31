<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\SitemapBuilder;
use App\Support\Seo\PublicSeo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * NOV-PUBLIC-01/02: robots.txt и sitemap.xml.
 *
 * robots.txt — правила из файла в корне репозитория (единственный источник,
 * его проверяет PublicRobotsConsistencyTest), но строка Sitemap всегда
 * формируется из config('seo.public_url'), чтобы robots и sitemap указывали на
 * один хост. sitemap.xml строится из базы (App\Services\Seo\SitemapBuilder):
 * статического списка больше нет. Корень проекта вне document root (public/),
 * поэтому файлы отдаются маршрутами; если веб-сервер позже начнёт отдавать
 * public/robots.txt, статический файл просто возьмёт верх.
 */
class SeoFileController extends Controller
{
    public function robots(): Response
    {
        $path = base_path('robots.txt');

        abort_unless(is_file($path), 404);

        $lines = preg_split('/\R/', rtrim((string) file_get_contents($path)));
        $lines = array_values(array_filter($lines, fn ($line) => stripos(ltrim($line), 'sitemap:') !== 0));

        $body = rtrim(implode("\n", $lines)) . "\n\nSitemap: " . PublicSeo::absoluteUrl('/sitemap.xml') . "\n";

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(SitemapBuilder $builder): Response
    {
        try {
            $xml = $builder->toXml();
        } catch (\Throwable $e) {
            // Без базы (например, до миграций) пустую/неполную карту не отдаём:
            // поисковик повторит запрос позже. В лог — только класс исключения.
            Log::error('Sitemap generation failed', ['exception' => get_class($e)]);

            return response('', 503, ['Retry-After' => '300']);
        }

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }
}
