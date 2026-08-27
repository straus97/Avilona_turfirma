<?php

namespace App\Support;

/**
 * E1-A5-F1: единственный источник истины для очистки News.description от
 * исполняемой разметки. News.description заполняется из внешнего RSS-фида
 * (App\Services\News\RssNewsSyncService), поэтому это доверительная граница:
 * содержимое контролирует третья сторона.
 *
 * Используется одновременно:
 *  - на приёме (RssNewsSyncService) — чтобы новые/обновлённые строки не
 *    сохраняли опасную разметку;
 *  - при публичном рендере (resources/views/news/show.blade.php) — эшелон
 *    защиты для строк, сохранённых до появления этой очистки.
 *
 * allow-list по тегам и атрибутам: `<script>`, обработчики событий (on*),
 * `javascript:`-ссылки и активная встраиваемая разметка (iframe/object/embed
 * и т.п.) удаляются вместе с содержимым; неизвестные теги разворачиваются
 * (текст/дети сохраняются, сам тег и его атрибуты — нет).
 */
final class NewsHtmlSanitizer
{
    /** @var array<string,array<int,string>> тег => разрешённые атрибуты */
    private const ALLOWED_TAGS = [
        'p' => [],
        'br' => [],
        'b' => [],
        'strong' => [],
        'i' => [],
        'em' => [],
        'u' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'span' => [],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title'],
    ];

    /** Теги, удаляемые вместе со всем своим содержимым (не текстовые контейнеры). */
    private const STRIP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'video', 'audio', 'source', 'track',
        'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math',
        'link', 'meta', 'base', 'applet', 'frame', 'frameset', 'noscript', 'noframes',
    ];

    private const URL_ATTRIBUTES = ['href', 'src'];

    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Пустые ("void") HTML-элементы без закрывающего тега. Устаревший
     * HTML4-парсер libxml, используемый DOMDocument::loadHTML, не знает
     * HTML5-список void-элементов и без явного самозакрытия трактует,
     * например, `<embed src="...">` как открывающий тег без пары — из-за
     * этого все последующие узлы становятся его потомками и пропадают при
     * удалении элемента. Самозакрытие ниже — это только нормализация
     * разбора по фиксированному списку тегов, а не сама фильтрация:
     * allow-list тегов/атрибутов по-прежнему применяется на дереве DOM.
     */
    private const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    public static function sanitize(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $html = self::selfCloseVoidElements($html);

        $previousUseErrors = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument();
            $loaded = $dom->loadHTML(
                '<?xml encoding="UTF-8"><div>' . $html . '</div>',
                LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if ($loaded === false) {
                return '';
            }

            $xpath = new \DOMXPath($dom);
            $root = $xpath->query('/div')->item(0);
            if (!$root instanceof \DOMElement) {
                return '';
            }

            self::sanitizeChildren($root);

            $output = '';
            foreach (iterator_to_array($root->childNodes) as $child) {
                $output .= $dom->saveHTML($child);
            }

            return $output;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }
    }

    private static function sanitizeChildren(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if (!$child instanceof \DOMElement) {
                // Комментарии, processing instructions и т.п. — не текст, удаляем.
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::STRIP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            if (!array_key_exists($tag, self::ALLOWED_TAGS)) {
                // Неизвестный/неразрешённый тег: сохраняем текст/детей, убираем сам тег.
                self::sanitizeChildren($child);
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            self::sanitizeAttributes($child, self::ALLOWED_TAGS[$tag]);
            self::sanitizeChildren($child);
        }
    }

    /**
     * @param array<int,string> $allowedAttributes
     */
    private static function sanitizeAttributes(\DOMElement $element, array $allowedAttributes): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if (!in_array($name, $allowedAttributes, true)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if (in_array($name, self::URL_ATTRIBUTES, true) && !self::isSafeUrl($attribute->value)) {
                $element->removeAttribute($attribute->name);
            }
        }
    }

    private static function selfCloseVoidElements(string $html): string
    {
        $pattern = '/<(' . implode('|', self::VOID_ELEMENTS) . ')((?:\s+[^<>]*)?)>/i';

        return preg_replace_callback($pattern, static function (array $matches): string {
            $attributes = rtrim($matches[2]);

            if (str_ends_with($attributes, '/')) {
                return '<' . $matches[1] . $attributes . '>';
            }

            return '<' . $matches[1] . $attributes . ' />';
        }, $html);
    }

    /**
     * Позитивный allow-list, а не блокировка отдельных написаний "javascript".
     * `parse_url()` ненадёжен как граница безопасности: управляющие символы
     * внутри строки (TAB/LF/CR) ломают его разбор схемы так, что он часто
     * возвращает null для строк вида "java\tscript:alert(1)" — то есть
     * ситуация "схема не найдена" НЕ равнозначна "это безопасный
     * относительный URL". Правило ниже классифицирует схему сами, на
     * канонизированной копии значения, и по умолчанию отклоняет любую
     * неоднозначную (colon-содержащую) строку, если она явно не разрешённая
     * схема и не один из явно безопасных относительных случаев.
     */
    private static function isSafeUrl(string $url): bool
    {
        $canonical = self::canonicalizeForSchemeCheck($url);
        if ($canonical === '') {
            return false;
        }

        // protocol-relative ("//host/..."), root-relative ("/path"),
        // fragment ("#x") и query ("?x") ссылки не несут собственной схемы —
        // они резолвятся относительно текущей страницы и не могут сами по
        // себе исполнить произвольную схему.
        if (preg_match('/^(?:\/\/|\/|#|\?)/', $canonical) === 1) {
            return true;
        }

        $colonPos = strpos($canonical, ':');
        if ($colonPos === false) {
            // Ни двоеточия, ни ведущего разделителя — обычный относительный
            // путь ("news/1", "page.html").
            return true;
        }

        $delimiterPos = self::firstPathDelimiterPos($canonical);
        if ($delimiterPos !== null && $delimiterPos < $colonPos) {
            // Двоеточие встречается только после начала пути/query/fragment
            // ("page?x=1:2") — это не схема.
            return true;
        }

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+\-.]*):/', $canonical, $matches) !== 1) {
            // Двоеточие стоит раньше любого разделителя пути, но то, что ему
            // предшествует, не является синтаксически валидной схемой —
            // неоднозначно, по умолчанию отклоняем, а не угадываем.
            return false;
        }

        return in_array(strtolower($matches[1]), self::ALLOWED_URL_SCHEMES, true);
    }

    /**
     * Копия значения атрибута только для классификации схемы. Исходное
     * значение атрибута никогда не переписывается: URL либо принимается как
     * есть, либо атрибут удаляется целиком.
     *
     * Шаги ниже намеренно зеркалят предобработку URL, которую выполняют
     * браузеры (WHATWG URL: "remove all ASCII tab or newline", "remove
     * leading/trailing C0 control or space") — иначе TAB/LF/CR, в том числе
     * попавшие в значение атрибута через декодированные DOM-парсером
     * HTML-сущности (`&#x09;`, `&#9;`, `&Tab;` и т.п.), могут визуально
     * разбить строку "javascript" так, что наивная проверка схемы её не
     * узнает, хотя браузер после нормализации — узнает.
     */
    private static function canonicalizeForSchemeCheck(string $url): string
    {
        $withoutTabsAndNewlines = preg_replace('/[\x09\x0A\x0D]/', '', $url);
        $trimmed = preg_replace('/^[\x00-\x20]+|[\x00-\x20]+$/', '', $withoutTabsAndNewlines);

        return $trimmed;
    }

    private static function firstPathDelimiterPos(string $s): ?int
    {
        $positions = [];
        foreach (['/', '?', '#'] as $delimiter) {
            $pos = strpos($s, $delimiter);
            if ($pos !== false) {
                $positions[] = $pos;
            }
        }

        return $positions === [] ? null : min($positions);
    }
}
