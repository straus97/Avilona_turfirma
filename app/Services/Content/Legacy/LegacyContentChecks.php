<?php

namespace App\Services\Content\Legacy;

use App\Support\NewsHtmlSanitizer;

/**
 * NOV-PUBLIC-04A: проверки качества контента. Только обнаруживают и считают;
 * текст не переписывают и не исправляют — решение за владельцем.
 */
final class LegacyContentChecks
{
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];

    /** Теги, закрывающий тег которых HTML разрешает опускать. */
    private const OPTIONAL_CLOSE = ['p', 'li', 'dt', 'dd', 'tr', 'td', 'th', 'option', 'thead', 'tbody', 'tfoot'];

    private const OWN_HOSTS = ['avilona.ru', 'www.avilona.ru'];

    /** Классы LegacyUrlClassifier, при которых внутренняя ссылка считается устаревшей. */
    private const OUTDATED_CLASSES = ['no_route', 'legacy_numeric_id', 'document_missing', 'document_editable_source'];

    /** @param (\Closure(string): string)|null $classifyUrl путь[?query] => класс LegacyUrlClassifier */
    public function __construct(
        private readonly ?\Closure $classifyUrl = null,
        private readonly int $currentYear = 0,
    ) {
    }

    /**
     * @return array{flags: array<string, mixed>, images: array<int,string>}
     *         flags: код => true|список; images: локальные <img src> внутри HTML
     */
    public function html(string $html): array
    {
        $flags = [];

        if (preg_match('#<\s*(?:script|iframe|object|embed|form|style|link|meta|base|applet)\b#i', $html) === 1
            || preg_match('#<[^>]*\son[a-z]+\s*=#i', $html) === 1
            || preg_match('#(?:href|src)\s*=\s*["\']?\s*(?:javascript|vbscript|data)\s*:#i', $html) === 1) {
            $flags['unsafe_markup'] = true;
        }

        if (! $this->balanced($html)) {
            $flags['malformed_html'] = true;
        }

        $dropped = $this->droppedTags($html);
        if ($dropped !== []) {
            $flags['tags_dropped_by_sanitizer'] = $dropped;
        }

        $this->linkFlags($html, $flags);

        $text = $this->plainText($html);
        if (trim($text) === '') {
            $flags['empty_text'] = true;
        }
        foreach ($this->textFlags($text) as $code => $detail) {
            $flags[$code] = $detail;
        }

        return ['flags' => $flags, 'images' => $this->localImages($html)];
    }

    /** @return array<string, mixed> */
    public function text(string $text): array
    {
        return $this->textFlags($text);
    }

    public function title(string $title): array
    {
        $flags = [];
        if (str_contains($title, '<') || str_contains($title, '>')) {
            $flags['title_has_markup'] = true;
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $title) === 1) {
            $flags['title_has_control_chars'] = true;
        }
        foreach ($this->textFlags($title) as $code => $detail) {
            if ($code === 'possible_mojibake' || $code === 'replacement_character') {
                $flags[$code] = $detail;
            }
        }

        return $flags;
    }

    // ------------------------------------------------------------------

    private function plainText(string $html): string
    {
        $spaced = preg_replace('#<\s*/?\s*(?:p|br|li|div|h[1-6]|tr|td)\b[^>]*>#i', ' ', $html) ?? $html;

        return html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return array<string, mixed> */
    private function textFlags(string $text): array
    {
        $flags = [];

        if ($this->currentYear > 0 && preg_match_all('/(?<!\d)(20[0-2]\d)(?!\d)/', $text, $m) > 0) {
            $past = array_values(array_unique(array_filter($m[1], fn ($y) => (int) $y < $this->currentYear)));
            sort($past);
            if ($past !== []) {
                $flags['past_year_reference'] = array_slice($past, 0, 10);
            }
        }

        $prices = preg_match_all('/\d[\d\s.,]*\s*(?:₽|руб(?:л[а-я]*)?\.?|р\.|\$|€|USD|EUR|евро|долл[а-я]*)/iu', $text);
        if ($prices > 0) {
            $flags['price_reference'] = $prices;
        }

        $rules = preg_match_all('/(?:ПЦР|COVID|ковид|коронавирус|вакцинац|QR-?код|карантин|самоизоляц|безвиз|визов[а-я]+ режим|консульск[а-я]+ сбор|правила въезда)/iu', $text);
        if ($rules > 0) {
            $flags['rules_reference'] = $rules;
        }

        if (preg_match('/[ÐÑ][\x{00A0}-\x{00BF}\x{2018}-\x{203A}\x{20AC}\x{0152}-\x{017E}]/u', $text) === 1) {
            $flags['possible_mojibake'] = true;
        }
        if (str_contains($text, "\u{FFFD}")) {
            $flags['replacement_character'] = true;
        }

        return $flags;
    }

    /** @return array<int,string> теги, которые NewsHtmlSanitizer удалит из этого фрагмента */
    private function droppedTags(string $html): array
    {
        try {
            $clean = NewsHtmlSanitizer::sanitize($html);
        } catch (\Throwable) {
            return ['sanitizer_failed'];
        }

        $dropped = array_diff($this->tagNames($html), $this->tagNames($clean));
        sort($dropped);

        return array_values($dropped);
    }

    /** @return array<int,string> */
    private function tagNames(string $html): array
    {
        preg_match_all('#<\s*([a-zA-Z][a-zA-Z0-9]*)#', $html, $m);

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    private function balanced(string $html): bool
    {
        $html = preg_replace('#<!--.*?-->#s', '', $html) ?? $html;
        $html = preg_replace('#<!(?:doctype)[^>]*>#i', '', $html) ?? $html;

        $tag = '#<(/?)([a-zA-Z][a-zA-Z0-9]*)\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>#';
        $stack = [];

        if (preg_match_all($tag, $html, $all, PREG_SET_ORDER) > 0) {
            foreach ($all as $m) {
                $name = strtolower($m[2]);
                if ($m[1] === '') {
                    if (! in_array($name, self::VOID, true) && ! str_ends_with(rtrim($m[3]), '/')) {
                        $stack[] = $name;
                    }

                    continue;
                }
                if (in_array($name, self::VOID, true)) {
                    continue;
                }

                $at = array_search($name, array_reverse($stack, true), true);
                if ($at === false) {
                    return false;
                }
                foreach (array_slice($stack, $at + 1) as $inner) {
                    if (! in_array($inner, self::OPTIONAL_CLOSE, true)) {
                        return false;
                    }
                }
                $stack = array_slice($stack, 0, $at);
            }
        }

        foreach ($stack as $open) {
            if (! in_array($open, self::OPTIONAL_CLOSE, true)) {
                return false;
            }
        }

        $rest = preg_replace($tag, '', $html) ?? $html;

        return preg_match('#<\s*/?\s*[a-zA-Z]#', $rest) !== 1;
    }

    /** @param array<string, mixed> $flags */
    private function linkFlags(string $html, array &$flags): void
    {
        $pattern = '#<a\b[^>]*?\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))#i';
        if (preg_match_all($pattern, $html, $m, PREG_SET_ORDER) === 0) {
            return;
        }

        $insecure = $www = $outdated = 0;
        $examples = [];

        foreach ($m as $match) {
            $href = trim(html_entity_decode($match[1] ?: ($match[2] ?? '') ?: ($match[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($href === '' || $href[0] === '#' || preg_match('#^(?:mailto|tel):#i', $href) === 1) {
                continue;
            }

            $path = null;
            if (preg_match('#^(https?)://([^/?\#]+)([^\#]*)#i', $href, $u) === 1) {
                $host = strtolower($u[2]);
                if (strtolower($u[1]) === 'http') {
                    $insecure++;
                }
                if (! in_array($host, self::OWN_HOSTS, true)) {
                    continue;
                }
                if (str_starts_with($host, 'www.')) {
                    $www++;
                }
                $path = $u[3] === '' ? '/' : $u[3];
            } elseif ($href[0] === '/' && ! str_starts_with($href, '//')) {
                $path = $href;
            }

            if ($path !== null && $this->classifyUrl !== null
                && in_array(($this->classifyUrl)($path), self::OUTDATED_CLASSES, true)) {
                $outdated++;
                if (count($examples) < 3) {
                    $examples[] = mb_substr(parse_url($path, PHP_URL_PATH) ?: $path, 0, 120);
                }
            }
        }

        if ($insecure > 0) {
            $flags['insecure_http_link'] = $insecure;
        }
        if ($www > 0) {
            $flags['www_host_link'] = $www;
        }
        if ($outdated > 0) {
            $flags['outdated_internal_link'] = ['count' => $outdated, 'examples' => $examples];
        }
    }

    /** @return array<int,string> локальные пути <img src> (без https и data:) */
    private function localImages(string $html): array
    {
        if (preg_match_all('#<img\b[^>]*?\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i', $html, $m, PREG_SET_ORDER) === 0) {
            return [];
        }

        $out = [];
        foreach ($m as $match) {
            $src = trim(html_entity_decode($match[1] ?: ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($src === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $src) === 1) {
                continue;
            }
            $out[] = strtok($src, '?#') ?: $src;
        }

        return array_values(array_unique($out));
    }
}
