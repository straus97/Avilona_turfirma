<?php

namespace Tests\Unit;

use App\Services\Legal\LegalArchiveException;
use App\Services\Legal\LegalFragment;
use App\Services\Legal\LegalRegistry;
use PHPUnit\Framework\TestCase;

/**
 * NOV-PUBLIC-LEGAL-01 S1-A: closed-grammar legal fragment validator (App\Services\Legal\LegalFragment).
 * Pure PHPUnit, no application boot. Every fragment here is synthetic text written with explicit "\n"
 * bytes, so the owner's core.autocrlf=true checkout cannot change the fixtures.
 */
class LegalFragmentTest extends TestCase
{
    private const POLICY = '<a href="%%LEGAL_LINK:POLICY%%" target="_blank" rel="noopener">';
    private const TERMS = '<a href="%%LEGAL_LINK:TERMS%%" target="_blank" rel="noopener">';
    private const ASSETS = [
        'personal-data-policy' => 'documents/Policy_regarding_the_protection_and_processing_of_personal_data.pdf',
        'separate-services-terms' => 'documents/Public_offer_for_ticket_sales_and_hotel_bookings.pdf',
        'tour-product-terms' => 'documents/Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf',
        'travel-memo-general' => 'documents/Reminder_for_those_traveling_abroad_general.pdf',
        'user-agreement' => 'documents/User_Agreement.pdf',
    ];

    /** Same shape as the reviewed master inventory: title, subtitle, revision label, numbered sections, paragraphs. */
    private static function valid(): string
    {
        return "<h1>Synthetic consent title</h1>\n"
            . "<p>Synthetic subtitle line</p>\n"
            . "<p>Synthetic revision label S-1</p>\n"
            . "<h2>1. First synthetic section</h2>\n"
            . '<p>The first paragraph mentions the ' . self::POLICY . "synthetic policy</a> exactly once.</p>\n"
            . "<p>Contact: synthetic.desk+inquiry@example.invalid, «quoted» text — a dash, 'single' and \"double\" quotes.\n"
            . "The same paragraph continues on a second line.</p>\n"
            . "<h2>2. Second synthetic section</h2>\n"
            . '<p>See also ' . self::TERMS . "the synthetic terms</a>.</p>\n";
    }

    // ------------------------------------------------------------------------------------- valid input

    public function test_valid_structure_is_parsed_with_exact_headings_and_links(): void
    {
        $fragment = LegalFragment::parse(self::valid(), ['POLICY', 'TERMS']);

        $this->assertSame('Synthetic consent title', $fragment->title());
        $this->assertSame(['1. First synthetic section', '2. Second synthetic section'], $fragment->headings());
        $this->assertSame(['POLICY', 'TERMS'], $fragment->placeholders());
        $this->assertSame(['h1', 'p', 'p', 'h2', 'p', 'p', 'h2', 'p'], array_column($fragment->blocks(), 'tag'));
        $this->assertSame([
            ['text' => 'The first paragraph mentions the ', 'link' => null],
            ['text' => 'synthetic policy', 'link' => 'POLICY'],
            ['text' => ' exactly once.', 'link' => null],
        ], $fragment->blocks()[4]['parts']);
        $this->assertStringContainsString('synthetic.desk+inquiry@example.invalid', $fragment->blocks()[5]['parts'][0]['text']);
    }

    public function test_declared_order_does_not_matter_but_every_declared_placeholder_is_used(): void
    {
        $this->assertSame(['POLICY', 'TERMS'], LegalFragment::parse(self::valid(), ['TERMS', 'POLICY'])->placeholders());

        $noLinks = "<h1>Title</h1>\n<p>Only text.</p>\n";
        $this->assertSame([], LegalFragment::parse($noLinks, [])->placeholders());
    }

    public function test_registry_bound_rendering_escapes_text_and_resolves_the_registered_documents(): void
    {
        $raw = self::valid();
        $rendered = LegalFragment::parse($raw, ['POLICY', 'TERMS'])
            ->render(self::registry($raw), 'inquiry-consent-text', 'rev-0001');

        $this->assertSame('Synthetic consent title', $rendered['title']);
        $this->assertCount(7, $rendered['blocks'], 'the h1 is returned separately as the title');
        $this->assertSame(['tag' => 'h2', 'html' => '1. First synthetic section'], $rendered['blocks'][2]);
        $this->assertSame(
            'The first paragraph mentions the <a href="/' . self::ASSETS['personal-data-policy']
                . '" target="_blank" rel="noopener">synthetic policy</a> exactly once.',
            $rendered['blocks'][3]['html']
        );
        $this->assertStringContainsString('&#039;single&#039; and &quot;double&quot;', $rendered['blocks'][4]['html']);
        $this->assertSame(
            'See also <a href="/' . self::ASSETS['user-agreement'] . '" target="_blank" rel="noopener">the synthetic terms</a>.',
            $rendered['blocks'][6]['html']
        );
        $this->assertStringNotContainsString('%%LEGAL_LINK', implode('', array_column($rendered['blocks'], 'html')));
    }

    public function test_a_trusted_asset_root_only_prefixes_the_registered_asset_path(): void
    {
        $raw = self::valid();
        $fragment = LegalFragment::parse($raw, ['POLICY', 'TERMS']);
        $registry = self::registry($raw);

        foreach (['https://example.invalid', 'http://localhost:8000/base/app', '/base', 'https://example.invalid:8443/a_b-c~d'] as $root) {
            $this->assertTrue(LegalFragment::isSafeAssetRoot($root), $root);
            $html = implode('', array_column($fragment->render($registry, 'inquiry-consent-text', 'rev-0001', $root)['blocks'], 'html'));
            $this->assertStringContainsString('href="' . $root . '/' . self::ASSETS['personal-data-policy'] . '"', $html);
            $this->assertStringContainsString('href="' . $root . '/' . self::ASSETS['user-agreement'] . '"', $html);
        }
    }

    public function test_unsafe_asset_roots_are_rejected(): void
    {
        $raw = self::valid();
        $fragment = LegalFragment::parse($raw, ['POLICY', 'TERMS']);
        $registry = self::registry($raw);

        foreach ([
            'https://example.invalid/', '//evil.invalid', 'javascript:alert(1)', 'https://user@example.invalid',
            'https://example.invalid?x=1', 'https://example.invalid#x', '/base/../x', '/./x', 'base', 'ftp://example.invalid',
            "/base\n", '/a b', '"onmouseover="x', str_repeat('/a', 600),
        ] as $root) {
            $this->assertFalse(LegalFragment::isSafeAssetRoot($root), $root);
            $this->assertRenderRejected('fragment_asset_root', $fragment, $registry, 'inquiry-consent-text', 'rev-0001', $root);
        }
    }

    public function test_rendering_is_bound_to_the_exact_registered_page_version(): void
    {
        $raw = self::valid();
        $fragment = LegalFragment::parse($raw, ['POLICY', 'TERMS']);
        $registry = self::registry($raw);

        $other = LegalFragment::parse(str_replace('subtitle', 'substitute', $raw), ['POLICY', 'TERMS']);
        $this->assertRenderRejected('fragment_binding', $other, $registry, 'inquiry-consent-text', 'rev-0001');
        $this->assertRenderRejected('fragment_binding', $fragment, $registry, 'user-agreement', 'rev-0001');
        $this->assertRenderRejected('registry_version_id', $fragment, $registry, 'inquiry-consent-text', 'rev-0002');
        $fewerLinks = self::registry($raw, ['POLICY' => 'personal-data-policy']);
        $this->assertRenderRejected('fragment_binding', $fragment, $fewerLinks, 'inquiry-consent-text', 'rev-0001');

        $parameters = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            (new \ReflectionMethod(LegalFragment::class, 'render'))->getParameters()
        );
        $this->assertSame(['registry', 'documentKey', 'versionId', 'assetRoot'], $parameters, 'no caller-supplied link URL');
    }

    public function test_link_url_shape_accepts_plain_urls_and_rejects_unsafe_ones(): void
    {
        foreach ([
            '/documents/Synthetic.pdf', 'https://example.invalid:8443/base/documents/a_b-c~d.pdf', 'http://localhost/documents/x.pdf',
        ] as $href) {
            $this->assertTrue(LegalFragment::isSafeHref($href), $href);
            $this->assertSame(PREG_NO_ERROR, preg_last_error(), 'the URL pattern must compile and run');
        }
        foreach ([
            'javascript:alert(1)', '//evil.invalid/x.pdf', '/documents/../secret.pdf', '/documents/./x.pdf',
            'https://example.invalid/x.pdf?download=1', 'https://example.invalid/x.pdf#top', '/documents/x%2e.pdf',
            '/documents/a b.pdf', '/documents/x.pdf"onmouseover="x', 'documents/x.pdf', 'ftp://example.invalid/x.pdf',
            'https://user@example.invalid/x.pdf', "/documents/x.pdf\n", '',
        ] as $href) {
            $this->assertFalse(LegalFragment::isSafeHref($href), $href);
        }
    }

    // ------------------------------------------------------------------------------------- bytes

    public function test_encoding_and_size_are_strict(): void
    {
        $this->assertFragmentRejected('fragment_encoding', "<h1>T</h1>\n<p>Bad \xC3\x28 byte</p>\n", [], 'invalid UTF-8');
        $this->assertFragmentRejected('fragment_encoding', "\xEF\xBB\xBF<h1>T</h1>\n<p>x</p>\n", [], 'BOM');
        $this->assertFragmentRejected('fragment_encoding', "<h1>T</h1>\r\n<p>x</p>\r\n", [], 'CRLF');
        $this->assertFragmentRejected('fragment_encoding', "<h1>T</h1>\n<p>x</p>", [], 'missing final LF');
        $this->assertFragmentRejected('fragment_encoding', "<h1>T</h1>\n<p>x</p>\n\n", [], 'two final LF');
        $this->assertFragmentRejected('fragment_size', '', [], 'empty');
        $this->assertFragmentRejected('fragment_size', "<h1>T</h1>\n<p>" . str_repeat('x', LegalFragment::MAX_BYTES) . "</p>\n", [], 'oversized');
    }

    public function test_disallowed_characters_are_rejected(): void
    {
        foreach ([
            'tab' => "\t", 'control' => "\x01", 'delete' => "\x7F", 'C1 control' => "\u{0085}",
            'non-breaking space' => "\u{00A0}", 'narrow non-breaking space' => "\u{202F}", 'figure space' => "\u{2007}",
            'soft hyphen' => "\u{00AD}", 'zero-width space' => "\u{200B}", 'zero-width joiner' => "\u{200D}",
            'word joiner' => "\u{2060}", 'BOM inside' => "\u{FEFF}", 'left-to-right mark' => "\u{200E}",
            'right-to-left override' => "\u{202E}", 'isolate' => "\u{2066}", 'arabic letter mark' => "\u{061C}",
            'line separator' => "\u{2028}", 'variation selector' => "\u{FE0F}", 'private use' => "\u{E000}",
            'replacement character' => "\u{FFFD}", 'tag character' => "\u{E0041}", 'ideographic space' => "\u{3000}",
            'ogham space mark' => "\u{1680}", 'mongolian vowel separator' => "\u{180E}", 'invisible plus' => "\u{2064}",
            'arabic number sign' => "\u{0600}", 'kaithi number sign' => "\u{110BD}", 'musical format control' => "\u{1D173}",
            'language tag' => "\u{E0001}", 'interlinear anchor' => "\u{FFF9}", 'noncharacter FDD0' => "\u{FDD0}",
            'noncharacter FFFF' => "\u{FFFF}", 'noncharacter 1FFFE' => "\u{1FFFE}", 'noncharacter 8FFFF' => "\u{8FFFF}",
            'noncharacter EFFFF' => "\u{EFFFF}", 'noncharacter 10FFFF' => "\u{10FFFF}",
        ] as $label => $character) {
            $this->assertFragmentRejected('fragment_character', "<h1>T</h1>\n<p>a{$character}b</p>\n", [], $label);
        }

        $allowed = "Ordinary letters é ё ü, signs € — № § and a combining mark e\u{0301} stay allowed.";
        $this->assertSame('Title', LegalFragment::parse("<h1>Title</h1>\n<p>$allowed</p>\n", [])->title());
    }

    // ------------------------------------------------------------------------------------- markup and structure

    public function test_markup_outside_the_closed_grammar_is_rejected(): void
    {
        foreach ([
            '<div>x</div>', '<p>x<br></p>', '<p>x<br/></p>', '<script>x</script>', '<style>x</style>',
            '<iframe src="/x"></iframe>', '<img src="/x.png">', '<form>x</form>', '<P>x</P>', '<p class="x">x</p>',
            '<p onclick="x">x</p>', '<p>x<b>y</b></p>', '<!-- x --><p>x</p>', '<h3>x</h3>',
        ] as $body) {
            $this->assertFragmentRejected('fragment_markup', "<h1>T</h1>\n$body\n", [], $body);
        }
        foreach (['<p>a &amp; b</p>', '<p>a &#60; b</p>', '<p>a < b</p>', '<p>a > b</p>', '<p>x<</p>', '<p>a & b</p>'] as $body) {
            $this->assertFragmentRejected('fragment_markup', "<h1>T</h1>\n$body\n", [], $body);
        }
    }

    public function test_block_structure_is_enforced(): void
    {
        $cases = [
            'unclosed paragraph' => "<h1>T</h1>\n<p>x\n",
            'mismatched close' => "<h1>T</h1>\n<p>x</h2>\n",
            'nested paragraph' => "<h1>T</h1>\n<p>a<p>b</p></p>\n",
            'heading inside paragraph' => "<h1>T</h1>\n<p>a<h2>b</h2></p>\n",
            'missing h1' => "<p>x</p>\n",
            'h1 not first' => "<p>x</p>\n<h1>T</h1>\n",
            'repeated h1' => "<h1>T</h1>\n<h1>U</h1>\n<p>x</p>\n",
            'no paragraph' => "<h1>T</h1>\n",
            'h2 at the end' => "<h1>T</h1>\n<p>x</p>\n<h2>1. S</h2>\n",
            'empty section' => "<h1>T</h1>\n<h2>1. S</h2>\n<h2>2. S</h2>\n<p>x</p>\n",
            'no separator' => "<h1>T</h1><p>x</p>\n",
            'blank line between blocks' => "<h1>T</h1>\n\n<p>x</p>\n",
            'text before first block' => "x<h1>T</h1>\n<p>x</p>\n",
            'text between blocks' => "<h1>T</h1>\nx\n<p>x</p>\n",
            'space between blocks' => "<h1>T</h1>\n <p>x</p>\n",
            'stray close' => "<h1>T</h1>\n</p>\n<p>x</p>\n",
            'only a line feed' => "\n",
        ];
        foreach ($cases as $label => $raw) {
            $this->assertFragmentRejected('fragment_structure', $raw, [], $label);
        }
    }

    public function test_text_must_be_nonempty_and_canonically_spaced(): void
    {
        $cases = [
            'empty paragraph' => "<h1>T</h1>\n<p></p>\n",
            'empty heading' => "<h1></h1>\n<p>x</p>\n",
            'leading space' => "<h1>T</h1>\n<p> x</p>\n",
            'trailing space' => "<h1>T</h1>\n<p>x </p>\n",
            'only spaces' => "<h1>T</h1>\n<p>   </p>\n",
            'double space' => "<h1>T</h1>\n<p>a  b</p>\n",
            'empty line inside' => "<h1>T</h1>\n<p>a\n\nb</p>\n",
            'space before line break' => "<h1>T</h1>\n<p>a \nb</p>\n",
            'space after line break' => "<h1>T</h1>\n<p>a\n b</p>\n",
            'leading line break' => "<h1>T</h1>\n<p>\nx</p>\n",
            'line break in heading' => "<h1>T</h1>\n<h2>a\nb</h2>\n<p>x</p>\n",
            'line break in title' => "<h1>T\nU</h1>\n<p>x</p>\n",
        ];
        foreach ($cases as $label => $raw) {
            $this->assertFragmentRejected('fragment_text', $raw, [], $label);
        }
    }

    // ------------------------------------------------------------------------------------- anchors and placeholders

    public function test_anchor_literal_and_placement_are_exact(): void
    {
        $markup = [
            'single quotes' => "<a href='%%LEGAL_LINK:POLICY%%' target='_blank' rel='noopener'>",
            'attribute order' => '<a target="_blank" href="%%LEGAL_LINK:POLICY%%" rel="noopener">',
            'extra attribute' => '<a href="%%LEGAL_LINK:POLICY%%" target="_blank" rel="noopener" class="x">',
            'duplicate attribute' => '<a href="%%LEGAL_LINK:POLICY%%" href="%%LEGAL_LINK:POLICY%%" target="_blank" rel="noopener">',
            'missing rel' => '<a href="%%LEGAL_LINK:POLICY%%" target="_blank">',
            'other target' => '<a href="%%LEGAL_LINK:POLICY%%" target="_self" rel="noopener">',
            'encoded attribute name' => '<a h&#114;ef="%%LEGAL_LINK:POLICY%%" target="_blank" rel="noopener">',
            'direct URL' => '<a href="https://example.invalid/x.pdf" target="_blank" rel="noopener">',
            'javascript URL' => '<a href="javascript:alert(1)" target="_blank" rel="noopener">',
            'uppercase tag' => '<A href="%%LEGAL_LINK:POLICY%%" target="_blank" rel="noopener">',
            'extra whitespace' => '<a  href="%%LEGAL_LINK:POLICY%%" target="_blank" rel="noopener">',
            'line break in tag' => "<a href=\"%%LEGAL_LINK:POLICY%%\"\ntarget=\"_blank\" rel=\"noopener\">",
        ];
        foreach ($markup as $label => $open) {
            $this->assertFragmentRejected('fragment_markup', "<h1>T</h1>\n<p>See {$open}policy</a>.</p>\n", ['POLICY'], $label);
        }

        $structure = [
            'link in title' => '<h1>' . self::POLICY . "T</a></h1>\n<p>x</p>\n",
            'link in heading' => "<h1>T</h1>\n<h2>" . self::POLICY . "S</a></h2>\n<p>x</p>\n",
            'nested link' => "<h1>T</h1>\n<p>" . self::POLICY . 'a' . self::POLICY . "b</a></a></p>\n",
            'unclosed link' => "<h1>T</h1>\n<p>" . self::POLICY . "policy</p>\n",
            'stray close' => "<h1>T</h1>\n<p>policy</a></p>\n",
            'link outside a block' => "<h1>T</h1>\n" . self::POLICY . "policy</a>\n<p>x</p>\n",
        ];
        foreach ($structure as $label => $raw) {
            $this->assertFragmentRejected('fragment_structure', $raw, ['POLICY'], $label);
        }

        $text = [
            'empty link text' => "<h1>T</h1>\n<p>See " . self::POLICY . "</a>.</p>\n",
            'padded link text' => "<h1>T</h1>\n<p>See" . self::POLICY . " policy</a>.</p>\n",
            'line break in link' => "<h1>T</h1>\n<p>See " . self::POLICY . "the\npolicy</a>.</p>\n",
        ];
        foreach ($text as $label => $raw) {
            $this->assertFragmentRejected('fragment_text', $raw, ['POLICY'], $label);
        }
    }

    public function test_placeholders_are_declared_used_exactly_once_and_well_formed(): void
    {
        $one = "<h1>T</h1>\n<p>See " . self::POLICY . "policy</a>.</p>\n";

        $this->assertFragmentRejected('fragment_placeholder', $one, [], 'undeclared');
        $this->assertFragmentRejected('fragment_placeholder', $one, ['POLICY', 'TERMS'], 'declared but unused');
        $this->assertFragmentRejected('fragment_placeholder', "<h1>T</h1>\n<p>" . self::POLICY . 'a</a> ' . self::POLICY . "b</a></p>\n", ['POLICY'], 'used twice');
        $this->assertFragmentRejected('fragment_placeholder', "<h1>T</h1>\n<p>See %%LEGAL_LINK:POLICY%% here.</p>\n", ['POLICY'], 'misplaced in text');
        $this->assertFragmentRejected('fragment_placeholder', "<h1>T</h1>\n<p>100% synthetic</p>\n", [], 'percent sign in text');
        $this->assertFragmentRejected('fragment_placeholder', "<h1>T</h1>\n<p>See <a href=\"%%LEGAL_LINK:policy%%\" target=\"_blank\" rel=\"noopener\">p</a>.</p>\n", ['POLICY'], 'malformed name');
        $this->assertFragmentRejected('fragment_placeholder', "<h1>T</h1>\n<p>See <a href=\"%%LEGAL_LINK:%%\" target=\"_blank\" rel=\"noopener\">p</a>.</p>\n", [], 'empty name');
        $this->assertFragmentRejected('fragment_placeholder', $one, ['POLICY', 'POLICY'], 'duplicate declaration');
        $this->assertFragmentRejected('fragment_placeholder', $one, ['policy'], 'malformed declaration');
        $this->assertFragmentRejected('fragment_placeholder', $one, ['A' => 'POLICY'], 'declarations must be a list');

        $this->assertSame(['POLICY'], LegalFragment::parse($one, ['POLICY'])->placeholders());
    }

    // ------------------------------------------------------------------------------------- helpers

    private function assertFragmentRejected(string $reason, string $raw, array $declared, string $label = ''): void
    {
        try {
            LegalFragment::parse($raw, $declared);
        } catch (LegalArchiveException $e) {
            $this->assertSame($reason, $e->reason, "$label: " . $e->getMessage());

            return;
        }
        $this->fail("$label: fragment must be rejected with $reason");
    }

    private function assertRenderRejected(string $reason, LegalFragment $fragment, LegalRegistry $registry, string $key, string $versionId, string $root = ''): void
    {
        try {
            $fragment->render($registry, $key, $versionId, $root);
        } catch (LegalArchiveException $e) {
            $this->assertSame($reason, $e->reason, "$root: " . $e->getMessage());

            return;
        }
        $this->fail("rendering must be rejected with $reason: $key/$versionId $root");
    }

    /**
     * A synthetic canonical registry whose consent version pins exactly these fragment bytes.
     *
     * @param array<string, string> $links placeholder => target document key
     */
    private static function registry(string $fragment, array $links = ['POLICY' => 'personal-data-policy', 'TERMS' => 'user-agreement']): LegalRegistry
    {
        $documents = [];
        foreach (LegalRegistry::DOCUMENT_KEYS as $key) {
            $isPage = $key === 'inquiry-consent-text';
            $type = $isPage ? LegalRegistry::CONTENT_HTML : LegalRegistry::CONTENT_PDF;
            $bytes = $isPage ? $fragment : "synthetic $key";
            $records = [];
            if ($isPage) {
                foreach ($links as $placeholder => $target) {
                    $records[] = ['placeholder' => $placeholder, 'document_key' => $target, 'version_id' => 'rev-0001'];
                }
            }
            $documents[] = [
                'key' => $key,
                'surface' => $isPage
                    ? ['type' => 'page', 'route_name' => 'personal_data_consent.info']
                    : ['type' => 'public-file', 'asset_path' => self::ASSETS[$key], 'repository_path' => 'public/' . self::ASSETS[$key]],
                'versions' => [[
                    'version_id' => 'rev-0001',
                    'locked' => true,
                    'approval_reference' => 'SYN-APR-1',
                    'content_type' => $type,
                    'path' => LegalRegistry::artifactPath($key, 'rev-0001', $type),
                    'sha256' => hash('sha256', $bytes),
                    'bytes' => strlen($bytes),
                    'links' => $records,
                ]],
                'activations' => [['version_id' => 'rev-0001', 'reference' => 'SYN-ACT-1', 'effective_from' => '2027-01-15']],
                'restrictions' => [],
            ];
        }

        return LegalRegistry::parse(json_encode(['format' => 1, 'hash_algorithm' => 'sha256', 'documents' => $documents],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    }
}
