<?php

namespace App\Services\Seo;

use App\Models\Article;
use App\Models\Countries_image;
use App\Models\Destination_image;
use App\Models\News;
use App\Models\OurClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * NOV-PUBLIC-03A: классификация исторических URL относительно ТЕКУЩИХ публичных
 * маршрутов. Только чтение; сеть не используется; в базу заглядывает лишь по
 * явной просьбе ($checkDatabase) и только запросом exists() по slug.
 *
 * Классификатор ничего не придумывает: он различает, что маршрут действительно
 * отдаёт тот же документ (A/B), что уже уходит постоянным редиректом по доказанной
 * идентичности содержимого (C), а что требует данных владельца (D/E). Адрес
 * назначения для числовых id стран и для пропавших slug не вычисляется никогда.
 *
 * Коды результата (поле class):
 *   supported_page            A  статическая страница, адрес не менялся
 *   supported_list            A  список (первая страница)
 *   supported_pagination      A  ?page=N, N>=2 на пагинируемом списке (404, если страниц меньше)
 *   redirect_first_page       C  ?page=1/нечисло/0 -> тот же путь без page (301, доказано)
 *   page_param_ignored        B  ?page=N на непагинируемой странице: тот же документ, canonical без page
 *   supported_slug            A  маршрут /{раздел}/{slug} существует; наличие записи зависит от контента
 *   slug_present / slug_missing  (только с --check-db) запись есть / записи нет (D)
 *   legacy_numeric_id         D  /countries/123: соответствие id->slug НЕ выводится из репозитория
 *   document_file             A  файл в public/documents существует по тому же адресу
 *   document_editable_source  E  .doc/.docx закрыты от выдачи (решение владельца N-4)
 *   document_missing          D  файла по этому адресу нет
 *   tracking_param            B  utm/прочие параметры: тот же документ, canonical без них
 *   private_or_service        —  приватный/служебный маршрут: никогда не редиректится
 *   no_route                  D  такого маршрута нет (честный 404)
 *   foreign_host / invalid_url —  не наш адрес / не разобран
 */
class LegacyUrlClassifier
{
    /** Разрешённые хосты старого сайта (канонический хост решает владелец, N-5). */
    private const OWN_HOSTS = ['avilona.ru', 'www.avilona.ru'];

    /** Публичные GET-маршруты, на которые ссылается классификатор (остальные считаются приватными). */
    private const STATIC_PAGE_ROUTES = [
        'home.index', 'tours.index', 'about_company.index', 'employees.index', 'awards.index',
        'contact.index', 'travel_dictionary.index', 'cookies.info', 'personal_data_consent.info',
        'review_personal_data_consent.info', 'review_publication_consent.info', 'news.rss',
        'seo.robots', 'seo.sitemap',
    ];

    /** Списки без пагинации: ?page=N на них ничего не меняет. */
    private const UNPAGINATED_LIST_ROUTES = ['countries.index', 'destination.index'];

    /** Пагинируемые списки (с middleware public.pagination). */
    private const PAGINATED_LIST_ROUTES = [
        'review.index', 'helpful_news.index', 'interesting_articles.index', 'for_our_clients.index',
    ];

    /** Маршруты записей: имя маршрута => модель с колонкой slug. */
    private const DETAIL_ROUTES = [
        'countries.show_countries_image' => Countries_image::class,
        'destinations.show_destinations_image' => Destination_image::class,
        'helpful_news_id.index' => News::class,
        'helpful_information.show_interesting_news' => Article::class,
        'helpful_information.show_special' => OurClient::class,
    ];

    public function __construct(private readonly Router $router)
    {
    }

    /**
     * @return array{input: string, path: string, query: string, class: string, route: string, action: string}
     */
    public function classify(string $input, bool $checkDatabase = false): array
    {
        $input = trim($input);
        $result = ['input' => $input, 'path' => '', 'query' => '', 'class' => 'invalid_url', 'route' => '', 'action' => 'ignore'];

        $parts = $this->parse($input);
        if ($parts === null) {
            return $result;
        }

        $result['path'] = $parts['path'];
        $result['query'] = $parts['query'];

        if ($parts['host'] !== null && ! in_array($parts['host'], self::OWN_HOSTS, true)) {
            return ['class' => 'foreign_host', 'action' => 'ignore'] + $result;
        }

        $route = $this->match($parts['path']);
        if ($route === null) {
            return $this->documentOrMissing($result);
        }

        $name = (string) $route->getName();
        $result['route'] = $name;

        if (isset(self::DETAIL_ROUTES[$name])) {
            return $this->detail($result, $name, (string) $route->parameter('slug'), $checkDatabase);
        }

        $isPaginated = in_array($name, self::PAGINATED_LIST_ROUTES, true);
        $isList = $isPaginated || in_array($name, self::UNPAGINATED_LIST_ROUTES, true);

        if (! $isList && ! in_array($name, self::STATIC_PAGE_ROUTES, true)) {
            return ['class' => 'private_or_service', 'action' => 'never_redirect'] + $result;
        }

        return $this->pageQuery($result, $parts['params'], $isPaginated, $isList);
    }

    /** @return array{host: ?string, path: string, query: string, params: array<string, mixed>}|null */
    private function parse(string $input): ?array
    {
        if ($input === '' || strlen($input) > 2048 || preg_match('/[\x00-\x1F\x7F\s]/', $input) === 1) {
            return null;
        }

        $host = null;
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $input) === 1) {
            if (preg_match('#^https?://#i', $input) !== 1) {
                return null;
            }
            $host = strtolower((string) parse_url($input, PHP_URL_HOST));
            if ($host === '') {
                return null;
            }
            $path = (string) (parse_url($input, PHP_URL_PATH) ?? '/');
            $query = (string) (parse_url($input, PHP_URL_QUERY) ?? '');
        } elseif (str_starts_with($input, '/') && ! str_starts_with($input, '//')) {
            $hash = strpos($input, '#');
            $clean = $hash === false ? $input : substr($input, 0, $hash);
            $q = strpos($clean, '?');
            $path = $q === false ? $clean : substr($clean, 0, $q);
            $query = $q === false ? '' : substr($clean, $q + 1);
        } else {
            return null;
        }

        $path = rawurldecode($path === '' ? '/' : $path);
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        parse_str($query, $params);

        return ['host' => $host, 'path' => $path, 'query' => $query, 'params' => $params];
    }

    private function match(string $path): ?LaravelRoute
    {
        try {
            $route = $this->router->getRoutes()->match(Request::create($path, 'GET'));
        } catch (HttpExceptionInterface) {
            return null;
        }

        return $route;
    }

    /** Нет маршрута: файл в public/documents или честный 404. */
    private function documentOrMissing(array $result): array
    {
        $path = $result['path'];

        if (str_starts_with($path, '/documents/')) {
            $relative = substr($path, strlen('/documents/'));
            $isFlat = $relative !== '' && ! str_contains($relative, '/') && ! str_contains($relative, '..');
            $isEditable = preg_match('/\.docx?$/i', $relative) === 1;

            if ($isFlat && $isEditable) {
                return ['class' => 'document_editable_source', 'action' => 'blocked_pending_owner_decision'] + $result;
            }

            if ($isFlat && is_file(public_path('documents/' . $relative))) {
                return ['class' => 'document_file', 'action' => 'none'] + $result;
            }

            return ['class' => 'document_missing', 'action' => 'owner_evidence'] + $result;
        }

        return ['class' => 'no_route', 'action' => 'serve_404'] + $result;
    }

    private function detail(array $result, string $name, string $slug, bool $checkDatabase): array
    {
        // Число в сегменте slug — это не slug, а прежний числовой id: id -> slug из репозитория не выводится.
        if ($slug !== '' && ctype_digit($slug)) {
            return ['class' => 'legacy_numeric_id', 'action' => 'owner_evidence'] + $result;
        }

        if ($checkDatabase) {
            $model = self::DETAIL_ROUTES[$name];
            $exists = $model::query()->where('slug', $slug)->exists();

            return ['class' => $exists ? 'slug_present' : 'slug_missing', 'action' => $exists ? 'none' : 'owner_evidence'] + $result;
        }

        return ['class' => 'supported_slug', 'action' => 'none'] + $result;
    }

    /** @param array<string, mixed> $params */
    private function pageQuery(array $result, array $params, bool $isPaginated, bool $isList): array
    {
        if (array_key_exists('page', $params)) {
            if ($isPaginated) {
                $page = filter_var($params['page'], FILTER_VALIDATE_INT);

                return $page === false || $page < 2
                    ? ['class' => 'redirect_first_page', 'action' => 'redirect_301'] + $result
                    : ['class' => 'supported_pagination', 'action' => 'none'] + $result;
            }

            return ['class' => 'page_param_ignored', 'action' => 'none'] + $result;
        }

        if ($params !== []) {
            return ['class' => 'tracking_param', 'action' => 'none'] + $result;
        }

        return ['class' => $isList ? 'supported_list' : 'supported_page', 'action' => 'none'] + $result;
    }

    /** Цель постоянного редиректа для redirect_first_page (иначе null): тот же путь без page. */
    public static function firstPageTarget(array $classified): ?string
    {
        if (($classified['class'] ?? '') !== 'redirect_first_page') {
            return null;
        }

        parse_str($classified['query'], $params);
        unset($params['page']);

        return $classified['path'] . ($params === [] ? '' : '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }
}
