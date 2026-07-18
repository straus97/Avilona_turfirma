<?php

namespace App\Services\News;

use App\Models\News;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Синхронизация новостей из внешнего RSS-фида.
 *
 * Логика вынесена из публичного GET-запроса (HelpfulNewsController) в явную
 * операцию, вызываемую artisan-командой news:sync-rss.
 *
 * Гарантии:
 *  - пишет только реальные колонки таблицы news (никакого `content`);
 *  - идемпотентна: существующая запись сопоставляется по link, её id и slug
 *    сохраняются даже при изменении заголовка в фиде;
 *  - учитывает soft-deleted строки при поиске по link и при проверке уникальности slug;
 *  - вся запись выполняется в одной транзакции — при любой ошибке изменений не остаётся;
 *  - не логирует сама (единый владелец логирования — команда), а бросает \DomainException.
 */
class RssNewsSyncService
{
    private const FEED_URL = 'https://www.turizm.ru/news/rss/yandex/';
    private const TIMEOUT = 15;
    private const CONTENT_NS = 'http://purl.org/rss/1.0/modules/content/';
    private const MAX_STRING = 255;

    /**
     * @return array{synced:int,skipped:array<int,string>}
     *
     * @throws \DomainException при сетевой ошибке, некорректном XML или неоднозначных данных
     */
    public function sync(): array
    {
        $xml = $this->fetch();
        $parsed = $this->parseAndNormalize($xml);
        $persisted = $this->persist($parsed['candidates']);

        return [
            'synced' => $persisted['synced'],
            'skipped' => array_merge($parsed['skipped'], $persisted['skipped']),
        ];
    }

    /**
     * Загрузка фида. Бросает \DomainException при недоступности/не-2xx.
     */
    private function fetch(): string
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get(self::FEED_URL);
        } catch (\Throwable $e) {
            throw new \DomainException('RSS feed request failed: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new \DomainException('RSS feed returned HTTP ' . $response->status());
        }

        return $response->body();
    }

    /**
     * Безопасный разбор XML и нормализация всех элементов (без обращений к базе).
     *
     * @return array{candidates:array<int,array<string,mixed>>,skipped:array<int,string>}
     *
     * @throws \DomainException при некорректном или пустом фиде
     */
    private function parseAndNormalize(string $xml): array
    {
        $previousUseErrors = libxml_use_internal_errors(true);

        try {
            $doc = new \DOMDocument();
            // LIBXML_NONET — запретить любые сетевые обращения при разборе;
            // внешние сущности не включаем (нет LIBXML_NOENT).
            $loaded = $doc->loadXML($xml, LIBXML_NONET);
            $items = $doc->getElementsByTagName('item');

            if ($loaded === false || $items->length === 0) {
                throw new \DomainException('Malformed or empty RSS feed');
            }

            $candidates = [];
            $skipped = [];
            foreach ($items as $item) {
                $result = $this->normalizeItem($item);
                if (isset($result['candidate'])) {
                    $candidates[] = $result['candidate'];
                } else {
                    $skipped[] = $result['skip'];
                }
            }

            return ['candidates' => $candidates, 'skipped' => $skipped];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }
    }

    /**
     * Нормализация одного <item> с валидацией ограничений колонок.
     * Возвращает либо ['candidate' => [...]] с реальными колонками,
     * либо ['skip' => reason] для элементов без title/link или со слишком
     * длинным link (идентификатор усекать нельзя).
     *
     * @return array{candidate?:array<string,mixed>,skip?:string}
     */
    private function normalizeItem(\DOMElement $item): array
    {
        $title = $this->firstValue($item, 'title');
        $link = $this->firstValue($item, 'link');

        // Обязательные поля-идентификаторы.
        if ($title === null || $title === '' || $link === null || $link === '') {
            return ['skip' => 'item missing title or link'];
        }
        // link — идентификатор, усекать нельзя: пропускаем слишком длинный.
        if (mb_strlen($link) > self::MAX_STRING) {
            return ['skip' => 'link exceeds ' . self::MAX_STRING . ' chars: ' . mb_substr($link, 0, 60) . '…'];
        }

        // title — отображаемое поле, безопасно усечь до 255.
        if (mb_strlen($title) > self::MAX_STRING) {
            $title = mb_substr($title, 0, self::MAX_STRING);
        }

        $encoded = $this->firstValueNS($item, self::CONTENT_NS, 'encoded');
        $description = $encoded !== null ? str_replace('<p>&nbsp;</p>', '', $encoded) : '';

        $image = $this->extractImage($item, $encoded);
        // image nullable string(255): некорректный/слишком длинный URL не храним усечённым.
        if ($image !== null && (mb_strlen($image) > self::MAX_STRING || !preg_match('#^https?://#i', $image))) {
            $image = null;
        }

        $pubDateRaw = $this->firstValue($item, 'pubDate');
        $pubDate = null;
        if ($pubDateRaw !== null && $pubDateRaw !== '') {
            $timestamp = strtotime($pubDateRaw);
            $pubDate = $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
        }

        return [
            'candidate' => [
                'title' => $title,
                'link' => $link,
                'description' => $description,
                'image' => $image,
                'pub_date' => $pubDate,
            ],
        ];
    }

    /**
     * Запись кандидатов в одной транзакции.
     *
     * @param array<int,array<string,mixed>> $candidates
     * @return array{synced:int,skipped:array<int,string>}
     *
     * @throws \DomainException при неоднозначных данных (несколько активных строк на один link)
     */
    private function persist(array $candidates): array
    {
        return DB::transaction(function () use ($candidates) {
            $synced = 0;
            $skipped = [];
            $reservedSlugs = [];

            foreach ($candidates as $candidate) {
                $link = $candidate['link'];
                $rows = News::withTrashed()->where('link', $link)->get();
                $active = $rows->whereNull('deleted_at');

                if ($active->count() > 1) {
                    // Неоднозначные существующие данные — не трогаем произвольную строку.
                    throw new \DomainException("Ambiguous news data: multiple active rows for link {$link}");
                }

                if ($active->count() === 1) {
                    // Обновление на месте: id и slug сохраняем (стабильный публичный URL).
                    $row = $active->first();
                    $row->fill([
                        'title' => $candidate['title'],
                        'description' => $candidate['description'],
                        'image' => $candidate['image'],
                        'pub_date' => $candidate['pub_date'],
                    ]);
                    $row->save();
                    $synced++;
                    continue;
                }

                if ($rows->isNotEmpty()) {
                    // Активных нет, но есть soft-deleted строка с этим link:
                    // не восстанавливаем автоматически и не создаём дубликат.
                    $skipped[] = $link;
                    continue;
                }

                // Новая запись: генерируем slug, не конфликтующий с активными,
                // soft-deleted строками и уже зарезервированными в этом батче.
                News::create([
                    'title' => $candidate['title'],
                    'link' => $link,
                    'description' => $candidate['description'],
                    'image' => $candidate['image'],
                    'pub_date' => $candidate['pub_date'],
                    'slug' => $this->generateUniqueSlug($candidate['title'], $reservedSlugs),
                ]);
                $synced++;
            }

            return ['synced' => $synced, 'skipped' => $skipped];
        });
    }

    /**
     * Уникальный slug: base, base-2, base-3, ... Проверяет активные и soft-deleted
     * строки (withTrashed) и зарезервированные в памяти slug текущего батча.
     *
     * @param array<int,string> $reserved
     */
    private function generateUniqueSlug(string $title, array &$reserved): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'news';
        }
        // Оставляем запас под числовой суффикс в пределах string(255).
        $base = mb_substr($base, 0, 250);

        $slug = $base;
        $i = 1;
        while (in_array($slug, $reserved, true) || News::withTrashed()->where('slug', $slug)->exists()) {
            $i++;
            $slug = $base . '-' . $i;
        }

        $reserved[] = $slug;

        return $slug;
    }

    private function firstValue(\DOMElement $item, string $tag): ?string
    {
        $node = $item->getElementsByTagName($tag)->item(0);

        return $node !== null ? trim($node->nodeValue) : null;
    }

    private function firstValueNS(\DOMElement $item, string $namespace, string $localName): ?string
    {
        $node = $item->getElementsByTagNameNS($namespace, $localName)->item(0);

        return $node !== null ? $node->nodeValue : null;
    }

    /**
     * URL картинки: сначала <enclosure url="...">, иначе первый <img src> из encoded-HTML.
     */
    private function extractImage(\DOMElement $item, ?string $encoded): ?string
    {
        $enclosure = $item->getElementsByTagName('enclosure')->item(0);
        if ($enclosure instanceof \DOMElement && $enclosure->getAttribute('url') !== '') {
            return $enclosure->getAttribute('url');
        }

        if ($encoded === null || $encoded === '') {
            return null;
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument();
            $dom->loadHTML(
                '<?xml encoding="UTF-8">' . $encoded,
                LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
            $images = $dom->getElementsByTagName('img');
            if ($images->length > 0) {
                $src = $images->item(0)->getAttribute('src');

                return $src !== '' ? $src : null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }

        return null;
    }
}
