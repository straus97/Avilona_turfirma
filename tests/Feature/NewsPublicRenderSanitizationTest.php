<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E1-A5-F1: публичная страница новости (helpful_news_id.index) не должна
 * отдавать исполняемую разметку из News.description, независимо от того,
 * когда/как эта строка была сохранена (текущая очистка на приёме в
 * RssNewsSyncService не переписывает уже существующие строки).
 * Проверяется через рендеринг: разбор DOM ответа, а не хрупкое совпадение
 * подстрок во всём документе.
 */
class NewsPublicRenderSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeNews(string $slug, string $description): News
    {
        return News::create([
            'title' => 'Sanitization Marker News',
            'slug' => $slug,
            'link' => 'https://example.com/news/' . $slug,
            'description' => $description,
            'image' => null,
            'pub_date' => '2024-01-01 10:00:00',
        ]);
    }

    /**
     * Разбирает полный ответ и возвращает узел .news-content — область, куда
     * попадает News.description. Остальная часть страницы (layout со своими
     * легитимными <script>-тегами и т.п.) намеренно не проверяется этим тестом.
     */
    private function newsContentNode(string $html): \DOMElement
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " news-content ")]')->item(0);

        $this->assertInstanceOf(\DOMElement::class, $node, '.news-content must be present in the response');

        return $node;
    }

    private function innerHtml(\DOMElement $element): string
    {
        $html = '';
        foreach (iterator_to_array($element->childNodes) as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    public function test_stored_malicious_html_does_not_render_as_executable_markup(): void
    {
        $description = '<p>Before script marker.</p>'
            . '<script>alert(1)</script>'
            . '<p>After script marker.</p>'
            . '<img src="x" onerror="alert(1)">'
            . '<p><a href="javascript:alert(1)">click marker</a></p>'
            . '<iframe src="https://evil.example"></iframe>'
            . '<object data="https://evil.example/object"></object>'
            . '<embed src="https://evil.example/embed">'
            . '<p>Trailing safe marker.</p>';

        $this->makeNews('render-malicious', $description);

        $response = $this->get(route('helpful_news_id.index', ['slug' => 'render-malicious']));
        $response->assertOk();

        // Читаемый текст остаётся видимым (контент не был выброшен целиком).
        $response->assertSee('Before script marker.', false);
        $response->assertSee('After script marker.', false);
        $response->assertSee('click marker', false);
        $response->assertSee('Trailing safe marker.', false);

        // Область .news-content — куда попадает News.description — не должна
        // содержать опасную разметку. Остальная часть страницы (layout) не
        // проверяется: там свои легитимные <script>-теги, не связанные с News.
        $newsContentNode = $this->newsContentNode($response->getContent());
        $newsContentHtml = $this->innerHtml($newsContentNode);

        $this->assertStringNotContainsString('<script', $newsContentHtml);
        $this->assertStringNotContainsString('alert(1)', $newsContentHtml);
        $this->assertStringNotContainsString('onerror', $newsContentHtml);
        $this->assertStringNotContainsString('javascript:', $newsContentHtml);
        $this->assertStringNotContainsString('<iframe', $newsContentHtml);
        $this->assertStringNotContainsString('<object', $newsContentHtml);
        $this->assertStringNotContainsString('<embed', $newsContentHtml);
        $this->assertStringNotContainsString('evil.example', $newsContentHtml);

        $xpath = new \DOMXPath($newsContentNode->ownerDocument);

        foreach (['script', 'iframe', 'object', 'embed'] as $tag) {
            $this->assertSame(
                0,
                $xpath->query(".//{$tag}", $newsContentNode)->length,
                "no <{$tag}> must survive rendering inside .news-content"
            );
        }

        foreach ($xpath->query('.//*[@*]', $newsContentNode) as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $this->assertStringStartsNotWith(
                    'on',
                    strtolower($attribute->name),
                    "no on*-event-handler attribute must survive rendering ({$attribute->name})"
                );

                if (in_array(strtolower($attribute->name), ['href', 'src'], true)) {
                    $this->assertStringNotContainsStringIgnoringCase('javascript:', $attribute->value);
                }
            }
        }
    }

    /**
     * E1-A5-F1 URL-obfuscation correction: an already-stored row (bypassing
     * ingestion entirely, via News::create directly) containing
     * TAB/entity/percent-obfuscated `javascript:`/`vbscript:`/`data:` hrefs
     * must not render those as active links — this is the render-time
     * defense-in-depth pass, independent of ingestion-time sanitization.
     */
    public function test_stored_obfuscated_scheme_href_does_not_render_as_active_link(): void
    {
        $description = '<p>Before render marker.</p>'
            . '<p><a href="java&#x09;script:alert(1)">obfuscated tab entity</a></p>'
            . '<p><a href="JAVASCRIPT:alert(1)">uppercase scheme</a></p>'
            . '<p><a href="vbscript:alert(1)">vbscript link</a></p>'
            . '<p><a href="data:text/html,evil">data link</a></p>'
            . '<p><img src="java&#x0A;script:alert(1)" alt="obfuscated image"></p>'
            . '<p><a href="https://example.com/safe">safe link</a></p>'
            . '<p><a href="/relative/path">relative link</a></p>'
            . '<p><a href="mailto:contact@example.com">mail link</a></p>'
            . '<p>After render marker.</p>';

        $this->makeNews('render-obfuscated', $description);

        $response = $this->get(route('helpful_news_id.index', ['slug' => 'render-obfuscated']));
        $response->assertOk();

        // Читаемый текст остаётся видимым.
        $response->assertSee('Before render marker.', false);
        $response->assertSee('obfuscated tab entity', false);
        $response->assertSee('uppercase scheme', false);
        $response->assertSee('vbscript link', false);
        $response->assertSee('data link', false);
        $response->assertSee('safe link', false);
        $response->assertSee('relative link', false);
        $response->assertSee('mail link', false);
        $response->assertSee('After render marker.', false);

        $newsContentNode = $this->newsContentNode($response->getContent());
        $xpath = new \DOMXPath($newsContentNode->ownerDocument);

        $hrefs = [];
        foreach ($xpath->query('.//a[@href]', $newsContentNode) as $a) {
            $hrefs[] = $a->getAttribute('href');
        }
        sort($hrefs);

        // Только явно разрешённые схемы/относительные формы пережили рендер.
        $this->assertSame(
            ['/relative/path', 'https://example.com/safe', 'mailto:contact@example.com'],
            $hrefs
        );

        // Единственное изображение в тесте было опасным (обфусцированная
        // схема) — src должен быть полностью удалён, а не переписан.
        $this->assertSame(0, $xpath->query('.//img[@src]', $newsContentNode)->length);
    }

    public function test_benign_markup_and_metadata_are_preserved(): void
    {
        $description = '<p>Readable paragraph.</p>'
            . '<p><a href="https://example.com/source">source link</a></p>'
            . '<p><a href="http://example.com/plain-http">plain http link</a></p>'
            . '<p><a href="mailto:contact@example.com">mail link</a></p>'
            . '<p><a href="/relative/path">relative link</a></p>'
            . '<p><a href="#section-two">hash link</a></p>'
            . '<p><a href="?utm_source=rss">query link</a></p>'
            . '<img src="https://example.com/image.jpg" alt="marker image">';

        $this->makeNews('render-benign', $description);

        $response = $this->get(route('helpful_news_id.index', ['slug' => 'render-benign']));
        $response->assertOk();

        $response->assertSee('Readable paragraph.', false);
        $response->assertSee('source link', false);
        $response->assertSee('https://example.com/source', false);
        $response->assertSee('https://example.com/image.jpg', false);

        $newsContentNode = $this->newsContentNode($response->getContent());
        $xpath = new \DOMXPath($newsContentNode->ownerDocument);

        $hrefs = [];
        foreach ($xpath->query('.//a[@href]', $newsContentNode) as $a) {
            $hrefs[] = $a->getAttribute('href');
        }
        sort($hrefs);

        $this->assertSame([
            '#section-two',
            '/relative/path',
            '?utm_source=rss',
            'http://example.com/plain-http',
            'https://example.com/source',
            'mailto:contact@example.com',
        ], $hrefs);

        $this->assertSame(
            1,
            $xpath->query('.//img[@src="https://example.com/image.jpg"]', $newsContentNode)->length
        );

        // Метаданные (E1-A4) продолжают строиться из controller-provided title/meta_description,
        // не изменённых этой правкой.
        $response->assertSee('Sanitization Marker News | avilona.ru', false);
        $response->assertSee('Последние новости о Sanitization Marker News', false);
    }

    // -----------------------------------------------------------------------
    // E2-A5-I1: публичное действие «Источник новости» — отдельная граница
    // рендера для поля News.link (НЕ description). News.link на приёме из RSS
    // проверяется только на непустоту/длину (RssNewsSyncService, вне слайса).
    // Публичная страница обязана показывать ссылку-источник только для
    // валидного внешнего http/https URL и никогда — для javascript:/data:/
    // vbscript:/protocol-трюков. NewsHtmlSanitizer этим не занимается и не
    // меняется; эти тесты фиксируют именно рендер-границу link.
    // -----------------------------------------------------------------------

    private function domFromResponse(string $html): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    public function test_news_detail_renders_source_action_for_a_safe_external_https_url(): void
    {
        News::create([
            'title' => 'Safe Source Link News',
            'slug' => 'safe-source-link-news',
            'link' => 'https://example.test/source',
            'description' => '<p>Readable safe-source body marker.</p>',
            'image' => null,
            'pub_date' => '2024-01-01 10:00:00',
        ]);

        $response = $this->get(route('helpful_news_id.index', ['slug' => 'safe-source-link-news']));
        $response->assertOk();
        $response->assertSee('Readable safe-source body marker.', false);

        $dom = $this->domFromResponse($response->getContent());
        $xpath = new \DOMXPath($dom);

        $main = $xpath->query('//main')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $main);

        $sourceLinks = iterator_to_array($xpath->query('.//a[@href="https://example.test/source"]', $main));
        $this->assertCount(1, $sourceLinks, 'a safe external https URL must render exactly one source action');

        /** @var \DOMElement $link */
        $link = $sourceLinks[0];
        $this->assertSame('_blank', $link->getAttribute('target'));

        $rel = preg_split('/\s+/', strtolower(trim($link->getAttribute('rel')))) ?: [];
        $this->assertContains('noopener', $rel, 'source action must carry rel="noopener"');
        $this->assertContains('noreferrer', $rel, 'source action must carry rel="noreferrer"');
    }

    public function test_news_detail_does_not_render_javascript_scheme_link_as_a_source_action(): void
    {
        News::create([
            'title' => 'Unsafe Source Link News',
            'slug' => 'unsafe-source-link-news',
            'link' => 'javascript:alert(1)',
            'description' => '<p>Readable unsafe-source body marker.</p>',
            'image' => null,
            'pub_date' => '2024-01-01 10:00:00',
        ]);

        $response = $this->get(route('helpful_news_id.index', ['slug' => 'unsafe-source-link-news']));
        $response->assertOk();
        // Страница по-прежнему рендерится, читаемый текст на месте.
        $response->assertSee('Readable unsafe-source body marker.', false);

        // Опасная схема не появляется в разметке вообще.
        $this->assertStringNotContainsString('javascript:alert(1)', $response->getContent());

        $dom = $this->domFromResponse($response->getContent());
        $xpath = new \DOMXPath($dom);

        $main = $xpath->query('//main')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $main);

        foreach ($xpath->query('.//a[@href]', $main) as $a) {
            /** @var \DOMElement $a */
            $this->assertStringStartsNotWith(
                'javascript:',
                strtolower(trim($a->getAttribute('href'))),
                'no javascript:-scheme href may be rendered as a link'
            );
        }
    }
}
