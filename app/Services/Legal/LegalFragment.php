<?php

namespace App\Services\Legal;

/**
 * NOV-PUBLIC-LEGAL-01 S1-A: validator and safe rendering data for a canonical legal HTML fragment
 * (`fragment.html` of the `inquiry-consent-text` document; installed only at S1-B).
 *
 * A closed grammar, not an HTML parser or sanitizer. The only markup is these literal tokens:
 *   <h1> </h1> <h2> </h2> <p> </p> </a>
 *   <a href="%%LEGAL_LINK:NAME%%" target="_blank" rel="noopener">   (NAME = [A-Z][A-Z0-9_]{0,31})
 *
 * Fragment   = Block ( LF Block )* LF                       — no BOM, no CR, exactly one final LF
 * Block      = "<h1>" Line "</h1>" | "<h2>" Line "</h2>" | "<p>" Inline "</p>"
 * Inline     = ( Text | Anchor )+                            — Text may contain single LFs (soft line breaks)
 * Anchor     = AnchorOpen Line "</a>"                        — only inside <p>, never nested
 *
 * Structure: exactly one <h1>, first; at least one <p>; every <h2> is followed by a <p>; no nesting; no empty
 * text. Text rules: no leading/trailing space or LF in a block or anchor, no double spaces, no space next to a
 * LF, no empty lines. Characters `<`, `>` and `&` never occur in text (so no entities, attributes or extra tags
 * can be authored) and `%` never occurs in text (placeholders exist only inside the anchor literal). Any other
 * Unicode is allowed, including the characters of ordinary e-mail addresses (`@`, `.`, `_`, `+`, `-`).
 * Rejected anywhere (explicit code point list, Unicode 15, so the result does not depend on the PCRE version):
 * controls other than LF (C0, DEL, C1), every space separator other than U+0020, line and paragraph separators,
 * format characters (soft hyphen, zero-width, bidirectional and tag characters among them), other invisible
 * fillers and variation selectors, private-use code points, all 66 noncharacters and the specials block
 * U+FFF0..U+FFFF. Unassigned code points are not rejected.
 *
 * Every declared placeholder is used exactly once and no undeclared placeholder occurs. Link targets are never
 * authored and never passed in by a caller: render() derives each URL from the validated registry binding of
 * the exact version these bytes are pinned to. This class does not call asset() or know a host name.
 * Rendering escapes all text and the final URL; stored bytes are never echoed raw.
 *
 * A PCRE evaluation error is an explicit rejection (`fragment_regex`), never "no match".
 */
final class LegalFragment
{
    public const MAX_BYTES = 65536;
    public const MAX_BLOCKS = 1000;
    public const MAX_LINKS = 16;
    public const MAX_HREF_BYTES = 2048;
    public const MAX_ASSET_ROOT_BYTES = 1024;

    private const BLOCK_TAGS = ['<h1>' => 'h1', '<h2>' => 'h2', '<p>' => 'p'];
    private const CLOSE_TAGS = ['</h1>' => 'h1', '</h2>' => 'h2', '</p>' => 'p'];
    private const ANCHOR_SHAPE = '/^<a href="%%LEGAL_LINK:([^"%]*)%%" target="_blank" rel="noopener">$/D';
    private const PLACEHOLDER_PATTERN = '/^[A-Z][A-Z0-9_]{0,31}$/D';
    private const DISALLOWED = '/['
        . '\x{0000}-\x{0009}\x{000B}-\x{001F}\x{007F}-\x{00A0}\x{00AD}\x{034F}\x{0600}-\x{0605}\x{061C}\x{06DD}'
        . '\x{070F}\x{0890}\x{0891}\x{08E2}\x{115F}\x{1160}\x{1680}\x{17B4}\x{17B5}\x{180B}-\x{180F}'
        . '\x{2000}-\x{200F}\x{2028}-\x{202F}\x{205F}-\x{206F}\x{2800}\x{3000}\x{3164}'
        . '\x{E000}-\x{F8FF}\x{FDD0}-\x{FDEF}\x{FE00}-\x{FE0F}\x{FEFF}\x{FFA0}\x{FFF0}-\x{FFFF}'
        . '\x{110BD}\x{110CD}\x{13430}-\x{1343F}\x{1BCA0}-\x{1BCA3}\x{1D173}-\x{1D17A}'
        . '\x{1FFFE}\x{1FFFF}\x{2FFFE}\x{2FFFF}\x{3FFFE}\x{3FFFF}\x{4FFFE}\x{4FFFF}\x{5FFFE}\x{5FFFF}'
        . '\x{6FFFE}\x{6FFFF}\x{7FFFE}\x{7FFFF}\x{8FFFE}\x{8FFFF}\x{9FFFE}\x{9FFFF}\x{AFFFE}\x{AFFFF}'
        . '\x{BFFFE}\x{BFFFF}\x{CFFFE}\x{CFFFF}\x{DFFFE}\x{DFFFF}\x{E0000}-\x{E0FFF}\x{EFFFE}\x{EFFFF}'
        . '\x{F0000}-\x{10FFFF}]/u';

    // The delimiter of the two URL patterns is "#": "~" is a legal path character inside their classes.
    private const ORIGIN = 'https?://[A-Za-z0-9](?:[A-Za-z0-9.-]{0,251}[A-Za-z0-9])?(?::[0-9]{1,5})?';
    private const SAFE_HREF = '#^(?:' . self::ORIGIN . ')?(?:/[A-Za-z0-9._~-]+)+$#D';
    private const SAFE_ASSET_ROOT = '#^(?:' . self::ORIGIN . ')?(?:/[A-Za-z0-9._~-]+)*$#D';

    /**
     * @param list<array{tag: string, parts: list<array{text: string, link: ?string}>}> $blocks
     * @param list<string> $placeholders in order of appearance
     */
    private function __construct(
        private readonly array $blocks,
        private readonly array $placeholders,
        private readonly string $sha256,
        private readonly int $bytes,
    ) {
    }

    /**
     * Validate exact fragment bytes against the placeholders declared by the registry version record.
     * Throws LegalArchiveException with a `fragment_*` reason.
     *
     * @param list<string> $declaredPlaceholders
     */
    public static function parse(string $raw, array $declaredPlaceholders): self
    {
        $declared = self::declared($declaredPlaceholders);
        self::assertEncoding($raw);

        $tokens = preg_split('/(<[^<>]*>)/', substr($raw, 0, -1), -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            throw new LegalArchiveException('fragment_regex', preg_last_error_msg());
        }

        $blocks = [];
        $block = null;
        $anchor = null;
        $used = [];
        $last = count($tokens) - 1;
        foreach ($tokens as $i => $token) {
            if ($i % 2 === 0) {
                if ($block === null) {
                    $separator = ($i === 0 || $i === $last) ? '' : "\n";
                    if ($token !== $separator) {
                        throw new LegalArchiveException('fragment_structure', 'blocks must be separated by exactly one LF');
                    }
                    continue;
                }
                if ($token !== '') {
                    self::assertTextCharacters($token, $anchor !== null || $block['tag'] !== 'p');
                    if ($anchor !== null) {
                        $anchor['text'] .= $token;
                    } else {
                        $block['parts'][] = ['text' => $token, 'link' => null];
                    }
                }
                continue;
            }

            if (isset(self::BLOCK_TAGS[$token])) {
                if ($block !== null) {
                    throw new LegalArchiveException('fragment_structure', 'nested block');
                }
                if (count($blocks) >= self::MAX_BLOCKS) {
                    throw new LegalArchiveException('fragment_size', 'too many blocks');
                }
                $block = ['tag' => self::BLOCK_TAGS[$token], 'parts' => []];
            } elseif (isset(self::CLOSE_TAGS[$token])) {
                if ($block === null || $anchor !== null || $block['tag'] !== self::CLOSE_TAGS[$token]) {
                    throw new LegalArchiveException('fragment_structure', 'unexpected ' . $token);
                }
                $blocks[] = self::closeBlock($block);
                $block = null;
            } elseif ($token === '</a>') {
                if ($anchor === null) {
                    throw new LegalArchiveException('fragment_structure', 'unexpected </a>');
                }
                self::assertLine($anchor['text'], 'anchor');
                $block['parts'][] = ['text' => $anchor['text'], 'link' => $anchor['name']];
                $anchor = null;
            } elseif (self::matches(self::ANCHOR_SHAPE, $token, $m)) {
                if ($block === null || $block['tag'] !== 'p' || $anchor !== null) {
                    throw new LegalArchiveException('fragment_structure', 'a link is allowed only directly inside <p>');
                }
                $name = $m[1];
                if (! self::matches(self::PLACEHOLDER_PATTERN, $name)) {
                    throw new LegalArchiveException('fragment_placeholder', 'malformed placeholder name');
                }
                if (! isset($declared[$name])) {
                    throw new LegalArchiveException('fragment_placeholder', "undeclared placeholder $name");
                }
                if (isset($used[$name])) {
                    throw new LegalArchiveException('fragment_placeholder', "placeholder $name used more than once");
                }
                $used[$name] = true;
                $anchor = ['name' => $name, 'text' => ''];
            } else {
                throw new LegalArchiveException('fragment_markup', 'markup outside the closed grammar');
            }
        }
        if ($block !== null) {
            throw new LegalArchiveException('fragment_structure', 'unclosed block');
        }

        self::assertStructure($blocks);
        $missing = array_diff(array_keys($declared), array_keys($used));
        if ($missing !== []) {
            throw new LegalArchiveException('fragment_placeholder', 'declared placeholder not used: ' . implode(', ', $missing));
        }

        return new self($blocks, array_keys($used), hash('sha256', $raw), strlen($raw));
    }

    /** Shape of a link URL: root-relative or http(s) absolute, plain path segments only. */
    public static function isSafeHref(string $href): bool
    {
        return $href !== '' && strlen($href) <= self::MAX_HREF_BYTES
            && self::matches(self::SAFE_HREF, $href) && self::hasNoDotSegment($href);
    }

    /**
     * Shape of a deployment asset root: "" (links stay root-relative), a base path, or an http(s) origin with
     * an optional base path; never a trailing slash, query, fragment or user info.
     */
    public static function isSafeAssetRoot(string $root): bool
    {
        return strlen($root) <= self::MAX_ASSET_ROOT_BYTES
            && self::matches(self::SAFE_ASSET_ROOT, $root) && self::hasNoDotSegment($root);
    }

    private static function hasNoDotSegment(string $url): bool
    {
        // The authority of an absolute URL cannot be "." or "..", so every "/"-separated part may be checked.
        $segments = explode('/', $url);

        return ! in_array('.', $segments, true) && ! in_array('..', $segments, true);
    }

    /** Plain text of the single <h1>. */
    public function title(): string
    {
        return self::plain($this->blocks[0]);
    }

    /** @return list<string> plain text of every <h2>, in order */
    public function headings(): array
    {
        $headings = [];
        foreach ($this->blocks as $block) {
            if ($block['tag'] === 'h2') {
                $headings[] = self::plain($block);
            }
        }

        return $headings;
    }

    /** @return list<array{tag: string, parts: list<array{text: string, link: ?string}>}> validated structure */
    public function blocks(): array
    {
        return $this->blocks;
    }

    /** @return list<string> placeholder names in order of appearance */
    public function placeholders(): array
    {
        return $this->placeholders;
    }

    /**
     * Registry-bound rendering data — the only way this class produces HTML.
     *
     * The registry (only obtainable from LegalRegistry::parse()) decides every link: URL = $assetRoot . "/" .
     * the validated asset path of the linked document. No caller can supply a URL for a placeholder.
     * The fragment bytes must equal the pin of the named page version, and its placeholders that record.
     *
     * $assetRoot is trusted deployment configuration, not user input: it must never come from a request
     * parameter, stored content or any other untrusted source. Its shape is still checked. It selects only the
     * origin and base path; it cannot change which document a placeholder opens.
     *
     * Returns the escaped title and the escaped body blocks after the <h1>; page chrome stays with the caller.
     *
     * @return array{title: string, blocks: list<array{tag: string, html: string}>}
     */
    public function render(LegalRegistry $registry, string $documentKey, string $versionId, string $assetRoot = ''): array
    {
        $version = $registry->version($documentKey, $versionId);
        if ($version['content_type'] !== LegalRegistry::CONTENT_HTML || $version['bytes'] !== $this->bytes
            || ! hash_equals($version['sha256'], $this->sha256)) {
            throw new LegalArchiveException('fragment_binding', "these bytes are not $documentKey/$versionId");
        }
        $targets = $registry->linkTargets($documentKey, $versionId);
        $declared = array_keys($targets);
        $used = $this->placeholders;
        sort($declared);
        sort($used);
        if ($declared !== $used) {
            throw new LegalArchiveException('fragment_binding', 'placeholders differ from the version record');
        }
        if (! self::isSafeAssetRoot($assetRoot)) {
            throw new LegalArchiveException('fragment_asset_root', 'unsafe asset root');
        }
        $hrefs = [];
        foreach ($targets as $placeholder => $target) {
            $href = $assetRoot . '/' . $target['asset_path'];
            if (! self::isSafeHref($href)) {
                throw new LegalArchiveException('fragment_href', 'unsafe resolved URL');
            }
            $hrefs[$placeholder] = $href;
        }

        $body = [];
        foreach (array_slice($this->blocks, 1) as $block) {
            $html = '';
            foreach ($block['parts'] as $part) {
                $text = self::escape($part['text']);
                $html .= $part['link'] === null
                    ? $text
                    : '<a href="' . self::escape($hrefs[$part['link']]) . '" target="_blank" rel="noopener">' . $text . '</a>';
            }
            $body[] = ['tag' => $block['tag'], 'html' => $html];
        }

        return ['title' => self::escape($this->title()), 'blocks' => $body];
    }

    /** @return array<string, true> */
    private static function declared(array $names): array
    {
        if (! array_is_list($names) || count($names) > self::MAX_LINKS) {
            throw new LegalArchiveException('fragment_placeholder', 'declared placeholders must be a short list');
        }
        $declared = [];
        foreach ($names as $name) {
            if (! is_string($name) || ! self::matches(self::PLACEHOLDER_PATTERN, $name) || isset($declared[$name])) {
                throw new LegalArchiveException('fragment_placeholder', 'invalid declared placeholder');
            }
            $declared[$name] = true;
        }

        return $declared;
    }

    private static function assertEncoding(string $raw): void
    {
        $length = strlen($raw);
        if ($length === 0 || $length > self::MAX_BYTES) {
            throw new LegalArchiveException('fragment_size', "$length bytes");
        }
        if (preg_match('//u', $raw) !== 1) {
            throw new LegalArchiveException('fragment_encoding', 'invalid UTF-8');
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            throw new LegalArchiveException('fragment_encoding', 'BOM');
        }
        if (str_contains($raw, "\r")) {
            throw new LegalArchiveException('fragment_encoding', 'CR');
        }
        if (! str_ends_with($raw, "\n") || str_ends_with($raw, "\n\n")) {
            throw new LegalArchiveException('fragment_encoding', 'exactly one final LF');
        }
        if (self::matches(self::DISALLOWED, $raw)) {
            throw new LegalArchiveException('fragment_character', 'disallowed character');
        }
    }

    /** preg_match that never turns an engine error into "no match". */
    private static function matches(string $pattern, string $subject, ?array &$found = null): bool
    {
        $result = preg_match($pattern, $subject, $found);
        if ($result === false) {
            throw new LegalArchiveException('fragment_regex', preg_last_error_msg());
        }

        return $result === 1;
    }

    private static function assertTextCharacters(string $text, bool $singleLine): void
    {
        if (strpbrk($text, '<>&') !== false) {
            throw new LegalArchiveException('fragment_markup', 'markup character in text');
        }
        if (str_contains($text, '%')) {
            throw new LegalArchiveException('fragment_placeholder', 'percent sign outside the link literal');
        }
        if ($singleLine && str_contains($text, "\n")) {
            throw new LegalArchiveException('fragment_text', 'line break in a heading or link');
        }
    }

    /** @return array{tag: string, parts: list<array{text: string, link: ?string}>} */
    private static function closeBlock(array $block): array
    {
        if ($block['parts'] === []) {
            throw new LegalArchiveException('fragment_text', 'empty block');
        }
        $text = implode('', array_column($block['parts'], 'text'));
        if ($block['tag'] === 'p') {
            self::assertSpacing($text, $block['tag']);
        } else {
            self::assertLine($text, $block['tag']);
        }

        return $block;
    }

    private static function assertLine(string $text, string $where): void
    {
        if (str_contains($text, "\n")) {
            throw new LegalArchiveException('fragment_text', "line break in $where");
        }
        self::assertSpacing($text, $where);
    }

    private static function assertSpacing(string $text, string $where): void
    {
        if ($text === '' || trim($text, " \n") !== $text) {
            throw new LegalArchiveException('fragment_text', "empty or padded $where");
        }
        foreach (['  ', "\n\n", " \n", "\n "] as $bad) {
            if (str_contains($text, $bad)) {
                throw new LegalArchiveException('fragment_text', "noncanonical spacing in $where");
            }
        }
    }

    private static function assertStructure(array $blocks): void
    {
        if ($blocks === [] || $blocks[0]['tag'] !== 'h1') {
            throw new LegalArchiveException('fragment_structure', 'the fragment must start with its single <h1>');
        }
        $paragraphs = 0;
        foreach ($blocks as $i => $block) {
            if ($i > 0 && $block['tag'] === 'h1') {
                throw new LegalArchiveException('fragment_structure', 'more than one <h1>');
            }
            if ($block['tag'] === 'h2' && ($blocks[$i + 1]['tag'] ?? null) !== 'p') {
                throw new LegalArchiveException('fragment_structure', 'every <h2> must be followed by a <p>');
            }
            $paragraphs += $block['tag'] === 'p' ? 1 : 0;
        }
        if ($paragraphs === 0) {
            throw new LegalArchiveException('fragment_structure', 'at least one <p> is required');
        }
    }

    private static function plain(array $block): string
    {
        return implode('', array_column($block['parts'], 'text'));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
    }
}
