<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Правило: shared layout (layouts.main) резолвит один канонический URL,
 * используемый одновременно для <link rel="canonical"> и <meta property="og:url">,
 * чтобы они не могли разойтись. По умолчанию используется текущий абсолютный
 * URL запроса без query-строки (url()->current()); дочерний @section('canonical_url', ...)
 * переопределяет значение. Раньше og:url был жёстко закодирован на адрес
 * главной страницы для всех страниц сайта — это и есть исправляемый дефект.
 *
 * Изолированные тестовые маршруты рендерятся через Blade::render() на inline
 * heredoc-шаблонах — без временных файлов представлений и без мутации View
 * finder. Значение override передаётся как обычные PHP-данные шаблона
 * (@section('canonical_url', $canonicalOverride)), а не как строковый литерал
 * внутри скомпилированного исходника, поэтому единственная точка экранирования
 * остаётся {{ $pageCanonicalUrl }} в самом layout.
 */
class PublicCanonicalMetadataConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/metadata-probe', function () {
            $template = <<<'BLADE'
@extends('layouts.main')

@section('content')
Probe Content
@endsection
BLADE;

            return response(Blade::render($template));
        })->name('__test.metadata-probe');

        Route::get('/metadata-probe-override', function () {
            $canonicalOverride = 'https://override.example.com/unique-canonical-marker-9091';

            $template = <<<'BLADE'
@extends('layouts.main')

@section('canonical_url', $canonicalOverride)

@section('content')
Override Probe
@endsection
BLADE;

            return response(Blade::render($template, [
                'canonicalOverride' => $canonicalOverride,
            ]));
        })->name('__test.metadata-probe-override');

        Route::get('/metadata-probe-escaped', function () {
            $canonicalOverride = 'https://override.example.com/?a=1&b="quoted"';

            $template = <<<'BLADE'
@extends('layouts.main')

@section('canonical_url', $canonicalOverride)

@section('content')
Escaped Probe
@endsection
BLADE;

            return response(Blade::render($template, [
                'canonicalOverride' => $canonicalOverride,
            ]));
        })->name('__test.metadata-probe-escaped');
    }

    // -----------------------------------------------------------------------
    // 1. Static source contract
    // -----------------------------------------------------------------------

    public function test_main_layout_defines_one_shared_canonical_url_for_canonical_and_open_graph(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/main.blade.php'));

        $this->assertSame(1, substr_count($layout, 'rel="canonical"'), 'Layout must contain exactly one rel="canonical" element');
        $this->assertSame(1, substr_count($layout, 'property="og:url"'), 'Layout must contain exactly one og:url element');

        $this->assertStringNotContainsString('content="https://avilona.ru/"', $layout);

        $this->assertMatchesRegularExpression('/rel="canonical"\s+href="\{\{\s*\$pageCanonicalUrl\s*\}\}"/', $layout);
        $this->assertMatchesRegularExpression('/property="og:url"\s+content="\{\{\s*\$pageCanonicalUrl\s*\}\}"/', $layout);

        $this->assertStringContainsString('url()->current()', $layout);
        $this->assertStringContainsString("yieldContent('canonical_url'", $layout);
    }

    // -----------------------------------------------------------------------
    // 2. Root page
    // -----------------------------------------------------------------------

    public function test_root_page_renders_matching_canonical_and_open_graph_urls(): void
    {
        $response = $this->get(route('home.index'));
        $response->assertOk();

        $expectedUrl = url('/');

        $doc = $this->parseHtml($response->getContent());
        $xpath = new \DOMXPath($doc);

        $canonicalNodes = $xpath->query('//link[@rel="canonical"]');
        $ogUrlNodes = $xpath->query('//meta[@property="og:url"]');

        $this->assertSame(1, $canonicalNodes->length);
        $this->assertSame(1, $ogUrlNodes->length);

        $this->assertSame($expectedUrl, $canonicalNodes->item(0)->getAttribute('href'));
        $this->assertSame($expectedUrl, $ogUrlNodes->item(0)->getAttribute('content'));
    }

    // -----------------------------------------------------------------------
    // 3. Non-root page uses its own current URL
    // -----------------------------------------------------------------------

    public function test_non_root_page_uses_its_own_current_url(): void
    {
        $response = $this->get('/metadata-probe');
        $response->assertOk();
        $response->assertDontSee('@endsection', false);

        $expectedUrl = url('/metadata-probe');

        $doc = $this->parseHtml($response->getContent());
        $xpath = new \DOMXPath($doc);

        $canonicalHref = $xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href');
        $ogUrlContent = $xpath->query('//meta[@property="og:url"]')->item(0)->getAttribute('content');

        $this->assertSame($expectedUrl, $canonicalHref);
        $this->assertSame($expectedUrl, $ogUrlContent);
        $this->assertNotSame(url('/'), $canonicalHref);
    }

    // -----------------------------------------------------------------------
    // 4. Query parameters excluded by default
    // -----------------------------------------------------------------------

    public function test_query_parameters_are_excluded_from_default_canonical_url(): void
    {
        $response = $this->get('/metadata-probe?utm_source=test&page=2');
        $response->assertOk();

        $expectedUrl = url('/metadata-probe');

        $doc = $this->parseHtml($response->getContent());
        $xpath = new \DOMXPath($doc);

        $canonicalHref = $xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href');
        $ogUrlContent = $xpath->query('//meta[@property="og:url"]')->item(0)->getAttribute('content');

        $this->assertSame($expectedUrl, $canonicalHref);
        $this->assertSame($expectedUrl, $ogUrlContent);
        $this->assertStringNotContainsString('utm_source', $canonicalHref);
        $this->assertStringNotContainsString('utm_source', $ogUrlContent);
        $this->assertStringNotContainsString('?', $canonicalHref);
        $this->assertStringNotContainsString('?', $ogUrlContent);
    }

    // -----------------------------------------------------------------------
    // 5. canonical_url section overrides both tags
    // -----------------------------------------------------------------------

    public function test_canonical_url_section_overrides_both_tags(): void
    {
        $response = $this->get('/metadata-probe-override');
        $response->assertOk();
        $response->assertDontSee('@endsection', false);

        $override = 'https://override.example.com/unique-canonical-marker-9091';

        $doc = $this->parseHtml($response->getContent());
        $xpath = new \DOMXPath($doc);

        $canonicalHref = $xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href');
        $ogUrlContent = $xpath->query('//meta[@property="og:url"]')->item(0)->getAttribute('content');

        $this->assertSame($override, $canonicalHref);
        $this->assertSame($override, $ogUrlContent);
        $this->assertNotSame(url('/metadata-probe-override'), $canonicalHref);
    }

    // -----------------------------------------------------------------------
    // 6. Output is HTML-escaped exactly once
    // -----------------------------------------------------------------------

    public function test_canonical_url_output_is_html_escaped(): void
    {
        $response = $this->get('/metadata-probe-escaped');
        $response->assertOk();

        $raw = $response->getContent();

        $unsafeValue = 'https://override.example.com/?a=1&b="quoted"';
        $expectedEscaped = htmlspecialchars($unsafeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Sanity: the layout's single escaping boundary must produce exactly
        // this form — never raw, never double-escaped.
        $this->assertSame(
            'https://override.example.com/?a=1&amp;b=&quot;quoted&quot;',
            $expectedEscaped
        );

        // 1. The raw unsafe form must never appear.
        $this->assertStringNotContainsString('href="' . $unsafeValue . '"', $raw);
        $this->assertStringNotContainsString('content="' . $unsafeValue . '"', $raw);

        // Double-escaping and literal unrendered Blade directives must not appear.
        $this->assertStringNotContainsString('&amp;amp;', $raw);
        $this->assertStringNotContainsString('&amp;quot;', $raw);
        $this->assertStringNotContainsString('@endsection', $raw);

        // 2 & 3. The canonical href and og:url content both carry the same, single, safely escaped form.
        $this->assertStringContainsString('href="' . $expectedEscaped . '"', $raw);
        $this->assertStringContainsString('content="' . $expectedEscaped . '"', $raw);

        // 4 & 5. Tag counts remain correct and the hostile `"` did not split into an extra attribute.
        $doc = $this->parseHtml($raw);
        $xpath = new \DOMXPath($doc);

        $canonicalNodes = $xpath->query('//link[@rel="canonical"]');
        $ogUrlNodes = $xpath->query('//meta[@property="og:url"]');

        $this->assertSame(1, $canonicalNodes->length);
        $this->assertSame(1, $ogUrlNodes->length);

        $this->assertSame(
            2,
            $canonicalNodes->item(0)->attributes->length,
            'Canonical link must only have rel and href attributes; a stray attribute would indicate broken escaping'
        );
        $this->assertSame(
            2,
            $ogUrlNodes->item(0)->attributes->length,
            'og:url meta must only have property and content attributes; a stray attribute would indicate broken escaping'
        );
    }

    // -----------------------------------------------------------------------
    // 7. Unrelated metadata contract preserved
    // -----------------------------------------------------------------------

    public function test_unrelated_existing_metadata_contract_is_preserved(): void
    {
        $response = $this->get(route('home.index'));
        $response->assertOk();

        $doc = $this->parseHtml($response->getContent());
        $xpath = new \DOMXPath($doc);

        $this->assertSame(
            'index, follow',
            $xpath->query('//meta[@name="robots"]')->item(0)->getAttribute('content')
        );
        $this->assertSame(
            'website',
            $xpath->query('//meta[@property="og:type"]')->item(0)->getAttribute('content')
        );
        $this->assertSame(
            'https://avilona.ru/img/logo.png',
            $xpath->query('//meta[@property="og:image"]')->item(0)->getAttribute('content')
        );
        $this->assertSame(
            'summary_large_image',
            $xpath->query('//meta[@name="twitter:card"]')->item(0)->getAttribute('content')
        );
        $this->assertSame(
            'https://www.avilona.ru/img/logo.png',
            $xpath->query('//meta[@name="twitter:image"]')->item(0)->getAttribute('content')
        );

        $titleNodes = $xpath->query('//title');
        $this->assertSame(1, $titleNodes->length);
        $this->assertNotSame('', trim($titleNodes->item(0)->textContent));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function parseHtml(string $html): \DOMDocument
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc;
    }
}
