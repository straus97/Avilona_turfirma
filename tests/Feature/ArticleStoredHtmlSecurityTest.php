<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * E1-FINAL-02 / E1-FINAL-03: Article.content остаётся богатым HTML, но:
 *  - на записи (admin + manager, store + update) очищается тем же allow-list
 *    санитайзером, что и News (App\Support\NewsHtmlSanitizer);
 *  - на публичном детальном рендере очищается ещё раз (эшелон защиты для
 *    исторических строк, сохранённых до появления очистки на записи);
 *  - анонс в списке — простой экранированный текст без активной разметки.
 */
class ArticleStoredHtmlSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private const MALICIOUS = '<p>Readable before marker.</p>'
        . '<script>alert(1)</script>'
        . '<p><strong>Bold survives marker.</strong></p>'
        . '<img src="x" onerror="alert(2)">'
        . '<p><a href="javascript:alert(3)">js link marker</a></p>'
        . '<iframe src="https://evil.example"></iframe>'
        . '<p>Readable after marker.</p>';

    private function makeUser(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );
        $user->roles()->attach($role->id);

        return $user;
    }

    private function articleContentNode(string $html): \DOMElement
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " article-content ")]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $node, '.article-content must be present on the detail page');

        return $node;
    }

    private function assertNodeIsClean(\DOMElement $node): void
    {
        $xpath = new \DOMXPath($node->ownerDocument);

        foreach (['script', 'iframe', 'object', 'embed'] as $tag) {
            $this->assertSame(0, $xpath->query(".//{$tag}", $node)->length, "no <{$tag}> may survive");
        }

        foreach ($xpath->query('.//*[@*]', $node) as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $this->assertStringStartsNotWith('on', strtolower($attribute->name), "no on*-handler may survive ({$attribute->name})");
                if (in_array(strtolower($attribute->name), ['href', 'src'], true)) {
                    $this->assertStringNotContainsStringIgnoringCase('javascript:', $attribute->value);
                }
            }
        }

        $inner = '';
        foreach (iterator_to_array($node->childNodes) as $child) {
            $inner .= $node->ownerDocument->saveHTML($child);
        }
        $this->assertStringNotContainsString('<script', $inner);
        $this->assertStringNotContainsString('onerror', $inner);
        $this->assertStringNotContainsString('javascript:', $inner);
        $this->assertStringNotContainsString('evil.example', $inner);
    }

    // -----------------------------------------------------------------------
    // 1. Write path (admin + manager, store + update) sanitizes content
    // -----------------------------------------------------------------------

    public function test_admin_store_sanitizes_article_content(): void
    {
        $this->assertStoreSanitizes(Role::ADMIN, 'admin');
    }

    public function test_manager_store_sanitizes_article_content(): void
    {
        $this->assertStoreSanitizes(Role::MANAGER, 'manager');
    }

    public function test_admin_update_sanitizes_article_content(): void
    {
        $this->assertUpdateSanitizes(Role::ADMIN, 'admin');
    }

    public function test_manager_update_sanitizes_article_content(): void
    {
        $this->assertUpdateSanitizes(Role::MANAGER, 'manager');
    }

    private function assertStoreSanitizes(string $roleName, string $prefix): void
    {
        $user = $this->makeUser($roleName);

        $response = $this->actingAs($user)->post(route("cabinet.{$prefix}.articles.store"), [
            'title' => 'Security Store Marker ' . $prefix,
            'slug' => 'security-store-marker-' . $prefix,
            'content' => self::MALICIOUS,
        ]);
        $response->assertRedirect(route("cabinet.{$prefix}.articles"));
        $response->assertSessionHasNoErrors();

        $stored = Article::firstWhere('slug', 'security-store-marker-' . $prefix);
        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('<script', $stored->content);
        $this->assertStringNotContainsString('onerror', $stored->content);
        $this->assertStringNotContainsString('javascript:', $stored->content);
        $this->assertStringNotContainsString('<iframe', $stored->content);
        // Benign rich formatting is preserved at rest.
        $this->assertStringContainsString('<strong>', $stored->content);
        $this->assertStringContainsString('Readable before marker.', $stored->content);
    }

    private function assertUpdateSanitizes(string $roleName, string $prefix): void
    {
        $user = $this->makeUser($roleName);
        $article = Article::create([
            'title' => 'Update Target ' . $prefix,
            'slug' => 'update-target-' . $prefix,
            'content' => '<p>Clean original.</p>',
        ]);

        $response = $this->actingAs($user)->put(route("cabinet.{$prefix}.articles.update", $article), [
            'title' => 'Update Target ' . $prefix,
            'slug' => 'update-target-' . $prefix,
            'content' => self::MALICIOUS,
        ]);
        $response->assertRedirect(route("cabinet.{$prefix}.articles"));
        $response->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertStringNotContainsString('<script', $article->content);
        $this->assertStringNotContainsString('onerror', $article->content);
        $this->assertStringNotContainsString('javascript:', $article->content);
        $this->assertStringContainsString('<strong>', $article->content);
    }

    // -----------------------------------------------------------------------
    // 2. Detail page: historical malicious row (bypassing write sanitization)
    // -----------------------------------------------------------------------

    public function test_detail_page_sanitizes_historical_malicious_content(): void
    {
        Article::create([
            'title' => 'Historical Malicious Marker',
            'slug' => 'historical-malicious-marker',
            'content' => self::MALICIOUS,
        ]);

        $response = $this->get(route('helpful_information.show_interesting_news', ['slug' => 'historical-malicious-marker']));
        $response->assertOk();

        // Readable text and benign formatting survive.
        $response->assertSee('Readable before marker.', false);
        $response->assertSee('Bold survives marker.', false);
        $response->assertSee('Readable after marker.', false);
        $response->assertSee('js link marker', false);

        $node = $this->articleContentNode($response->getContent());
        $this->assertNodeIsClean($node);

        $xpath = new \DOMXPath($node->ownerDocument);
        $this->assertSame(1, $xpath->query('.//strong', $node)->length, 'benign <strong> must survive on detail');
    }

    public function test_detail_page_renders_cms_authored_content_safely(): void
    {
        $user = $this->makeUser(Role::MANAGER);
        $this->actingAs($user)->post(route('cabinet.manager.articles.store'), [
            'title' => 'Authored Then Rendered Marker',
            'slug' => 'authored-then-rendered-marker',
            'content' => self::MALICIOUS,
        ])->assertRedirect();

        $response = $this->get(route('helpful_information.show_interesting_news', ['slug' => 'authored-then-rendered-marker']));
        $response->assertOk();

        $this->assertNodeIsClean($this->articleContentNode($response->getContent()));
    }

    // -----------------------------------------------------------------------
    // 3. Listing excerpt is plain escaped text, no live injected elements
    // -----------------------------------------------------------------------

    public function test_listing_excerpt_is_plain_text_without_live_markup(): void
    {
        Article::create([
            'title' => 'Listing Excerpt Marker',
            'slug' => 'listing-excerpt-marker',
            'content' => '<p>Visible excerpt words here.</p><script>alert(9)</script><img src="x" onerror="alert(9)">',
        ]);

        $response = $this->get(route('interesting_articles.index'));
        $response->assertOk();
        $response->assertSee('Visible excerpt words here.', false);

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);

        $card = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " card-text ")]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $card);

        $this->assertSame(0, $xpath->query('.//script', $card)->length);
        $this->assertSame(0, $xpath->query('.//img', $card)->length);
        $this->assertStringNotContainsString('alert(9)', $dom->saveHTML($card));
    }

    public function test_listing_shows_empty_state_when_no_articles(): void
    {
        $response = $this->get(route('interesting_articles.index'));
        $response->assertOk();
        $response->assertSee('Статьи пока не добавлены', false);
    }

    // -----------------------------------------------------------------------
    // 4. Robustness: nullable image must not emit a bare <img src="">
    // -----------------------------------------------------------------------

    public function test_null_image_article_does_not_emit_bare_image_on_listing_or_detail(): void
    {
        Article::create([
            'title' => 'Null Image Article Marker',
            'slug' => 'null-image-article-marker',
            'content' => '<p>Body text marker.</p>',
            'image' => null,
        ]);

        $listing = $this->get(route('interesting_articles.index'));
        $listing->assertOk();
        $listing->assertSee('Null Image Article Marker', false);
        $this->assertStringNotContainsString('src=""', $listing->getContent());

        $detail = $this->get(route('helpful_information.show_interesting_news', ['slug' => 'null-image-article-marker']));
        $detail->assertOk();
        $this->assertStringNotContainsString('src=""', $detail->getContent());
    }

    public function test_non_null_image_article_still_renders_image(): void
    {
        Article::create([
            'title' => 'Imaged Article Marker',
            'slug' => 'imaged-article-marker',
            'content' => '<p>Body text marker.</p>',
            'image' => 'https://example.com/article.jpg',
        ]);

        $detail = $this->get(route('helpful_information.show_interesting_news', ['slug' => 'imaged-article-marker']));
        $detail->assertOk();
        $detail->assertSee('https://example.com/article.jpg', false);
    }
}
