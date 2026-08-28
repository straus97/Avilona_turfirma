<?php
//этот файл помогает правильно выводить новости на сайт
namespace App\Helpers;

use App\Support\NewsHtmlSanitizer;
use Illuminate\Support\Str;

class TextHelper
{
    /**
     * E1-FINAL-02/03: безопасный текстовый анонс для списков (статьи и т.п.).
     * Богатый HTML не нужен: сначала прогоняем через проверенный санитайзер
     * (чтобы содержимое <script>/<style> не превратилось в видимый текст),
     * затем срезаем все теги и нормализуем пробелы. Результат обязан
     * выводиться через экранированный Blade `{{ }}`.
     */
    public static function plainExcerpt(?string $html, int $limit = 100): string
    {
        $sanitized = NewsHtmlSanitizer::sanitize((string) $html);
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($sanitized)) ?? '');

        return Str::limit($text, $limit);
    }

    public static function formatNewsDescription($html)
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $paragraphs = $doc->getElementsByTagName('p');

        if ($paragraphs->length > 0) {
            $firstParagraph = $paragraphs->item(0)->textContent;
            return mb_strimwidth($firstParagraph, 0, 150, "...");
        }

        return 'Описание отсутствует'; // Возвращаем пустую строку, если нет тегов <p>
    }
}
