<?php

namespace App\Support\Seo;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * NOV-PUBLIC-02: единая точка для публичных SEO-значений.
 *
 * Хост берётся из config('seo.public_url'), а не из запроса — canonical, og:url,
 * og:image, JSON-LD, sitemap.xml и robots.txt поэтому не могут разойтись между
 * собой и не подхватывают служебный хост. Класс ничего не пишет и не читает из
 * базы; описания строятся только из переданного (уже опубликованного) текста.
 */
class PublicSeo
{
    /** Мягкая граница описания: режем по границе предложения/слова, жёсткого лимита нет. */
    public const DESCRIPTION_SOFT_LENGTH = 220;

    /** Подтверждённые реквизиты (см. docs/NOV-PUBLIC-01 §10); адрес — фактический офис, не юридический. */
    public const LEGAL_NAME = 'ООО «Авилона»';
    public const BRAND_NAME = 'Авилона';
    public const EMAIL = 'avilonatur@bk.ru';
    public const TAX_ID = '7805502454';
    public const PHONES = ['+79219314345', '+79219842022'];
    public const OFFICE_POSTAL_CODE = '198261';
    public const OFFICE_LOCALITY = 'Санкт-Петербург';
    public const OFFICE_STREET = 'ул. Генерала Симоняка, д. 10';

    public static function baseUrl(): string
    {
        return rtrim((string) config('seo.public_url'), '/');
    }

    /** Абсолютный URL на публичном хосте: '/path' -> https://host/path; полный http(s) URL не меняется. */
    public static function absoluteUrl(string $pathOrUrl): string
    {
        if (preg_match('#^https?://#i', $pathOrUrl) === 1) {
            return $pathOrUrl;
        }

        return self::baseUrl() . '/' . ltrim($pathOrUrl, '/');
    }

    /** Атрибут запроса, который ставит middleware public.pagination на пагинируемых списках. */
    public const PAGINATED_ATTRIBUTE = 'seo.paginated';

    /**
     * Номер страницы пагинации: самостоятельной страницей считается только целое
     * >= 2 на пагинируемом списке (NormalizePublicPagination). На остальных
     * маршрутах параметр page ничего не меняет, поэтому всегда 1; значение
     * читается так же, как пагинатор Laravel (filter_var), чтобы canonical и
     * реально показанная страница не расходились.
     */
    public static function pageNumber(Request $request): int
    {
        if ($request->attributes->get(self::PAGINATED_ATTRIBUTE) !== true) {
            return 1;
        }

        $page = filter_var($request->query('page'), FILTER_VALIDATE_INT);

        if ($page !== false && $page >= 2 && $page <= 100000) {
            return $page;
        }

        return 1;
    }

    /**
     * Ссылка пагинации без лишнего ?page=1: страница 1 — это адрес без параметра
     * (с параметром он отвечает 301). Остальные параметры и их порядок сохраняются.
     */
    public static function pageLinkUrl(?string $url): string
    {
        $url = (string) $url;
        $queryStart = strpos($url, '?');

        if ($queryStart === false) {
            return $url;
        }

        $fragment = '';
        $hash = strpos($url, '#');
        if ($hash !== false) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        // Только отбрасываем пару page=1; остальные пары остаются как были, без перекодирования.
        $pairs = array_values(array_filter(
            explode('&', substr($url, $queryStart + 1)),
            fn (string $pair) => $pair !== 'page=1' && $pair !== ''
        ));

        return substr($url, 0, $queryStart) . ($pairs === [] ? '' : '?' . implode('&', $pairs)) . $fragment;
    }

    /**
     * Страница за пределами списка — не существующий документ: честный 404
     * (а не 200 с пустой страницей и собственным canonical). Первая страница
     * пустого списка остаётся обычной страницей. Вызывается контроллерами
     * пагинируемых списков.
     */
    public static function abortIfPageOutOfRange(LengthAwarePaginator $paginator): void
    {
        if ($paginator->currentPage() > max($paginator->lastPage(), 1)) {
            // Страница-404 не должна объявлять собственный ?page=N каноническим адресом.
            request()->attributes->remove(self::PAGINATED_ATTRIBUTE);

            abort(404);
        }
    }

    /**
     * Канонический URL: публичный хост + путь запроса. Query-параметры фильтров и
     * трекинга не входят; единственное исключение — ?page=N (N >= 2), потому что
     * это самостоятельная страница списка, а не дубль первой.
     */
    public static function canonicalUrl(Request $request): string
    {
        $path = '/' . ltrim($request->path(), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $url = self::baseUrl() . ($path === '/' ? '/' : $path);

        $page = self::pageNumber($request);

        return $page >= 2 ? $url . '?page=' . $page : $url;
    }

    /**
     * Краткое описание из фактического текста: без тегов и сущностей, пробелы
     * схлопнуты, обрезка по границе предложения/слова с многоточием. Пустой
     * источник даёт $fallback — текст при этом не выдумывается.
     */
    public static function describe(?string $html, string $fallback, int $soft = self::DESCRIPTION_SOFT_LENGTH): string
    {
        // Границы блоков превращаем в пробелы, иначе соседние абзацы склеятся в одно слово.
        $spaced = preg_replace('~</(?:p|div|li|h[1-6]|blockquote|tr|td)>|<br\s*/?>~i', ' ', (string) $html);
        $text = html_entity_decode(strip_tags((string) $spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return $fallback;
        }

        if (mb_strlen($text) <= $soft) {
            return $text;
        }

        $cut = mb_substr($text, 0, $soft);

        // Предпочитаем конец предложения во второй половине окна, иначе границу слова.
        if (preg_match('/^(.{' . (int) ($soft / 2) . ',}[.!?])\s/us', $cut . ' ', $m) === 1) {
            return $m[1];
        }

        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $soft / 2) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " ,;:-—") . '…';
    }

    /**
     * Абсолютный URL изображения для og:image. Хранимые значения — полные URL или
     * пути относительно public/. Относительный путь, которого нет на диске, не
     * публикуем (битая og:image хуже запасной): возвращается $default.
     */
    public static function imageUrl(?string $stored, string $default): string
    {
        $stored = trim((string) $stored);

        if ($stored === '' || str_starts_with($stored, '//') || str_contains($stored, '..')) {
            return $default;
        }

        if (preg_match('#^https://[^\s]+$#i', $stored) === 1) {
            return $stored;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $stored) === 1) {
            return $default; // http:, data:, javascript: и т.п. — не используем
        }

        $relative = ltrim($stored, '/');

        return is_file(public_path($relative)) ? self::absoluteUrl($relative) : $default;
    }

    public static function defaultImageUrl(): string
    {
        return self::absoluteUrl('img/logo.png');
    }

    /**
     * Описание организации для JSON-LD. Только подтверждённые факты: фактический
     * офис (не юридический адрес), e-mail, телефоны, ИНН. Без координат, часов
     * работы, рейтингов, отзывов, ценового диапазона, лицензий и соцсетей.
     *
     * @return array<string, mixed>
     */
    public static function businessIdentity(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'TravelAgency',
            '@id' => self::baseUrl() . '/#organization',
            'name' => self::BRAND_NAME,
            'legalName' => self::LEGAL_NAME,
            'url' => self::baseUrl() . '/',
            'logo' => self::defaultImageUrl(),
            'image' => self::defaultImageUrl(),
            'email' => self::EMAIL,
            'telephone' => self::PHONES,
            'taxID' => self::TAX_ID,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => self::OFFICE_STREET,
                'addressLocality' => self::OFFICE_LOCALITY,
                'postalCode' => self::OFFICE_POSTAL_CODE,
                'addressCountry' => 'RU',
            ],
        ];
    }

    /** JSON для вставки в <script type="application/ld+json">: теги и амперсанды экранированы. */
    public static function businessIdentityJson(): string
    {
        return json_encode(
            self::businessIdentity(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );
    }

    /** Заголовок страницы с пометкой пагинации, чтобы страницы списка не дублировали title первой. */
    public static function withPageSuffix(string $text, int $page): string
    {
        if ($page < 2 || mb_stripos($text, 'страница') !== false) {
            return $text;
        }

        return $text . ' — страница ' . $page;
    }

    /** Описание страницы списка №N: добавляем номер, чтобы страницы пагинации не дублировали description первой. */
    public static function withPageDescriptionSuffix(string $text, int $page): string
    {
        if ($page < 2 || mb_stripos($text, 'страница') !== false) {
            return $text;
        }

        return rtrim($text) . ' Страница ' . $page . '.';
    }

    /**
     * Описание страницы записи: «Название. Начало фактического текста». Если текста
     * нет, остаётся нейтральная фраза про сам материал — без выдуманных фактов.
     */
    public static function entityDescription(string $title, ?string $html, string $kind): string
    {
        $title = rtrim(trim($title), ".!?… ");
        $excerpt = self::describe($html, "", 200);

        if ($excerpt === "") {
            return $title . " — " . $kind . " на сайте туристической фирмы «Авилона» (Санкт-Петербург).";
        }

        return $title . ". " . $excerpt;
    }
}