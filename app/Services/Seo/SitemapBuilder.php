<?php

namespace App\Services\Seo;

use App\Models\Article;
use App\Models\Award;
use App\Models\Countries_image;
use App\Models\Destination_image;
use App\Models\Employee;
use App\Models\News;
use App\Models\OurClient;
use App\Models\Reviews;
use App\Support\Seo\PublicSeo;
use Illuminate\Support\Facades\Route;

/**
 * NOV-PUBLIC-02: sitemap.xml строится из реальных публичных данных, а не из
 * статического списка.
 *
 * Правило включения: URL попадает в карту, только если публичный маршрут его
 * действительно отдаёт со статусом 200 и страница индексируется. Условия
 * зеркалят запросы контроллеров: записи с мягким удалением и без slug не
 * включаются (Eloquent SoftDeletes исключает удалённые сам), списки без
 * записей — тоже (пустая страница не должна попадать в индекс), отзывы — только
 * опубликованные и не отозванные. В карту не попадают приватные маршруты,
 * PDF-документы (юридические файлы ждут решения владельца), фильтры и
 * дубли ?page=1. Страницы пагинации (?page=N, N >= 2) — самостоятельные
 * канонические URL, число страниц считается точно по числу записей.
 *
 * Хост всегда config('seo.public_url') (как canonical и robots.txt). <lastmod>
 * выводится только при SEO_SITEMAP_LASTMOD=true (см. config/seo.php).
 */
class SitemapBuilder
{
    /** Размер страницы публичных списков (совпадает с paginate(6) в контроллерах). */
    public const PER_PAGE = 6;

    /**
     * @return array<int, array{loc: string, lastmod: ?string}>
     */
    public function entries(): array
    {
        $entries = [];
        $limit = (int) config('seo.sitemap_max_urls', 45000);

        $add = function (string $path, ?string $lastmod = null) use (&$entries, $limit) {
            if (count($entries) < $limit) {
                $entries[$path] = ['loc' => PublicSeo::absoluteUrl($path), 'lastmod' => $lastmod];
            }
        };

        // Страницы без обязательной привязки к базе данных.
        foreach (['home.index', 'tours.index', 'contact.index', 'about_company.index', 'travel_dictionary.index'] as $name) {
            if (Route::has($name)) {
                $add($this->path($name));
            }
        }

        // Списки и их записи.
        $this->section($add, 'employees.index', Employee::query());
        $this->section($add, 'awards.index', Award::query());
        $this->section($add, 'countries.index', Countries_image::query(), 'countries.show_countries_image');
        $this->section($add, 'destination.index', Destination_image::query(), 'destinations.show_destinations_image');
        $this->section($add, 'helpful_news.index', News::query(), 'helpful_news_id.index', true);
        $this->section($add, 'interesting_articles.index', Article::query(), 'helpful_information.show_interesting_news', true);
        $this->section($add, 'for_our_clients.index', OurClient::query(), 'helpful_information.show_special', true);

        // Отзывы: тот же фильтр, что в Review\IndexController.
        $reviews = Reviews::where('is_published', 1)
            ->whereDoesntHave('reviewConsent', fn ($q) => $q->whereNotNull('withdrawn_at'));
        $reviewCount = (clone $reviews)->count();
        if ($reviewCount > 0 && Route::has('review.index')) {
            $add($this->path('review.index'));
            $this->addPages($add, $this->path('review.index'), $reviewCount);
        }

        return array_values($entries);
    }

    public function toXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($this->entries() as $entry) {
            $xml .= "    <url>\n        <loc>" . htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if ($entry['lastmod'] !== null) {
                $xml .= '        <lastmod>' . $entry['lastmod'] . "</lastmod>\n";
            }
            $xml .= "    </url>\n";
        }

        return $xml . "</urlset>\n";
    }

    /**
     * Список включается, если в нём есть записи; записи с slug — как детальные
     * страницы; при $paginated добавляются страницы ?page=2…N.
     */
    private function section(callable $add, string $indexRoute, $query, ?string $showRoute = null, bool $paginated = false): void
    {
        if (! Route::has($indexRoute)) {
            return;
        }

        $count = (clone $query)->count();
        if ($count === 0) {
            return;
        }

        $indexPath = $this->path($indexRoute);
        $add($indexPath);

        if ($paginated) {
            $this->addPages($add, $indexPath, $count);
        }

        if ($showRoute === null || ! Route::has($showRoute)) {
            return;
        }

        $withLastmod = (bool) config('seo.sitemap_lastmod');
        $columns = $withLastmod ? ['id', 'slug', 'updated_at'] : ['id', 'slug'];

        (clone $query)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderBy('id')
            ->select($columns)
            ->chunkById(500, function ($rows) use ($add, $showRoute, $withLastmod) {
                foreach ($rows as $row) {
                    $lastmod = $withLastmod && $row->updated_at ? $row->updated_at->toAtomString() : null;
                    $add($this->path($showRoute, ['slug' => $row->slug]), $lastmod);
                }
            });
    }

    private function addPages(callable $add, string $indexPath, int $count): void
    {
        $pages = (int) ceil($count / self::PER_PAGE);

        for ($page = 2; $page <= $pages; $page++) {
            $add($indexPath . '?page=' . $page);
        }
    }

    /** Путь маршрута без хоста (хост добавляет PublicSeo::absoluteUrl). */
    private function path(string $name, array $params = []): string
    {
        return route($name, $params, false);
    }
}
