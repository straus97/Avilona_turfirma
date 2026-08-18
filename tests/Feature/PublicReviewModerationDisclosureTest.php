<?php

namespace Tests\Feature;

use App\Models\ReviewConsent;
use App\Models\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 13 C4C: public disclosure of moderator-edited review content.
 *
 * Проверяет только публичное раскрытие маркера is_moderator_edited на
 * `/reviews` и тизере главной страницы: точный текст раскрытия, его
 * привязку к конкретному отзыву, отсутствие приватных полей (даты
 * модерации, согласие/доказательства) и сохранность экранирования
 * пользовательского контента. Серверная логика маркера уже покрыта
 * ReviewModerationEnforcementTest.php и здесь не проверяется повторно.
 */
class PublicReviewModerationDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private const DISCLOSURE_TEXT = 'Текст отзыва отредактирован модератором без изменения общего смысла.';

    // -----------------------------------------------------------------------
    // 1, 2. /reviews disclosure conditional on marker
    // -----------------------------------------------------------------------

    public function test_reviews_page_shows_disclosure_when_edited(): void
    {
        $this->makeReview('Edited Author', ['content' => 'EDITED-REVIEWS-MARKER-1', 'is_moderator_edited' => true]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee(self::DISCLOSURE_TEXT);
    }

    public function test_reviews_page_hides_disclosure_when_not_edited(): void
    {
        $this->makeReview('Unedited Author', ['content' => 'UNEDITED-REVIEWS-MARKER-2', 'is_moderator_edited' => false]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertDontSee(self::DISCLOSURE_TEXT);
    }

    // -----------------------------------------------------------------------
    // 3, 4. Homepage disclosure conditional on marker
    // -----------------------------------------------------------------------

    public function test_homepage_shows_disclosure_when_edited(): void
    {
        $this->makeReview('Edited Home Author', ['content' => 'EDITED-HOME-MARKER-3', 'is_moderator_edited' => true]);

        $response = $this->get(route('home.index'));

        $response->assertOk();
        $response->assertSee(self::DISCLOSURE_TEXT);
    }

    public function test_homepage_hides_disclosure_when_not_edited(): void
    {
        $this->makeReview('Unedited Home Author', ['content' => 'UNEDITED-HOME-MARKER-4', 'is_moderator_edited' => false]);

        $response = $this->get(route('home.index'));

        $response->assertOk();
        $response->assertDontSee(self::DISCLOSURE_TEXT);
    }

    // -----------------------------------------------------------------------
    // 5. Isolation: edited review's disclosure does not bleed onto unedited one
    // -----------------------------------------------------------------------

    public function test_reviews_page_isolates_disclosure_to_the_edited_review_only(): void
    {
        $this->makeReview('Isolation Edited Author', [
            'content' => 'ISOLATION-EDITED-MARKER-5',
            'is_moderator_edited' => true,
        ]);
        $this->makeReview('Isolation Unedited Author', [
            'content' => 'ISOLATION-UNEDITED-MARKER-5',
            'is_moderator_edited' => false,
        ]);

        $response = $this->get(route('review.index'));
        $response->assertOk();

        $html = $response->getContent();

        $unmoderatedFragment = $this->extractReviewsRowFragment($html, 'ISOLATION-UNEDITED-MARKER-5');
        $this->assertStringContainsString('ISOLATION-UNEDITED-MARKER-5', $unmoderatedFragment);
        $this->assertStringNotContainsString(self::DISCLOSURE_TEXT, $unmoderatedFragment);
        $this->assertStringNotContainsString('ISOLATION-EDITED-MARKER-5', $unmoderatedFragment);

        $moderatedFragment = $this->extractReviewsRowFragment($html, 'ISOLATION-EDITED-MARKER-5');
        $this->assertStringContainsString('ISOLATION-EDITED-MARKER-5', $moderatedFragment);
        $this->assertStringContainsString(self::DISCLOSURE_TEXT, $moderatedFragment);
        $this->assertStringNotContainsString('ISOLATION-UNEDITED-MARKER-5', $moderatedFragment);

        $disclosureCount = substr_count($html, self::DISCLOSURE_TEXT);
        $this->assertSame(1, $disclosureCount);
    }

    public function test_homepage_isolates_disclosure_to_the_edited_review_only(): void
    {
        $this->makeReview('Isolation Edited Home Author', [
            'content' => 'ISOLATION-EDITED-HOME-MARKER-6',
            'is_moderator_edited' => true,
        ]);
        $this->makeReview('Isolation Unedited Home Author', [
            'content' => 'ISOLATION-UNEDITED-HOME-MARKER-6',
            'is_moderator_edited' => false,
        ]);

        $response = $this->get(route('home.index'));
        $response->assertOk();

        $html = $response->getContent();

        $unmoderatedFragment = $this->extractHomeCardFragment($html, 'ISOLATION-UNEDITED-HOME-MARKER-6');
        $this->assertStringContainsString('ISOLATION-UNEDITED-HOME-MARKER-6', $unmoderatedFragment);
        $this->assertStringNotContainsString(self::DISCLOSURE_TEXT, $unmoderatedFragment);
        $this->assertStringNotContainsString('ISOLATION-EDITED-HOME-MARKER-6', $unmoderatedFragment);

        $moderatedFragment = $this->extractHomeCardFragment($html, 'ISOLATION-EDITED-HOME-MARKER-6');
        $this->assertStringContainsString('ISOLATION-EDITED-HOME-MARKER-6', $moderatedFragment);
        $this->assertStringContainsString(self::DISCLOSURE_TEXT, $moderatedFragment);
        $this->assertStringNotContainsString('ISOLATION-UNEDITED-HOME-MARKER-6', $moderatedFragment);

        $disclosureCount = substr_count($html, self::DISCLOSURE_TEXT);
        $this->assertSame(1, $disclosureCount);
    }

    // -----------------------------------------------------------------------
    // 6, 7. moderator_edited_at is never publicly rendered
    // -----------------------------------------------------------------------

    public function test_reviews_page_does_not_render_moderator_edited_at(): void
    {
        $distinctiveTimestamp = '2019-03-07 04:05:06';
        $this->makeReview('Timestamp Author', [
            'content' => 'TIMESTAMP-REVIEWS-MARKER-7',
            'is_moderator_edited' => true,
            'moderator_edited_at' => $distinctiveTimestamp,
        ]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertDontSee('2019-03-07');
        $response->assertDontSee('04:05:06');
    }

    public function test_homepage_does_not_render_moderator_edited_at(): void
    {
        $distinctiveTimestamp = '2019-03-07 04:05:06';
        $this->makeReview('Timestamp Home Author', [
            'content' => 'TIMESTAMP-HOME-MARKER-8',
            'is_moderator_edited' => true,
            'moderator_edited_at' => $distinctiveTimestamp,
        ]);

        $response = $this->get(route('home.index'));

        $response->assertOk();
        $response->assertDontSee('2019-03-07');
        $response->assertDontSee('04:05:06');
    }

    // -----------------------------------------------------------------------
    // 8. Private consent/evidence fields are not exposed by the disclosure
    // -----------------------------------------------------------------------

    public function test_public_pages_do_not_expose_private_consent_fields_alongside_disclosure(): void
    {
        $review = $this->makeReview('Consent Author', [
            'content' => 'CONSENT-PRIVACY-MARKER-9',
            'is_moderator_edited' => true,
        ]);

        ReviewConsent::create([
            'review_id' => $review->id,
            'evidence_id' => (string) \Illuminate\Support\Str::uuid(),
            'consent_full_name' => 'Secret Consent Full Name',
            'consent_email' => 'secret-consent@example.com',
            'user_agreement_accepted_at' => now(),
            'personal_data_consent_accepted_at' => now(),
            'review_publication_consent_accepted_at' => now(),
            'user_agreement_version' => 'disclosure-test-user-agreement',
            'personal_data_consent_version' => 'disclosure-test-personal-data-consent',
            'review_publication_consent_version' => 'disclosure-test-review-publication-consent',
            'publication_scope' => ['name', 'content'],
            'publication_conditions' => 'Secret publication conditions text.',
            'review_payload_sha256' => hash('sha256', 'disclosure-test-payload-' . $review->id),
        ]);

        $reviewsResponse = $this->get(route('review.index'));
        $reviewsResponse->assertOk();
        $reviewsResponse->assertSee(self::DISCLOSURE_TEXT);
        $reviewsResponse->assertDontSee('Secret Consent Full Name');
        $reviewsResponse->assertDontSee('secret-consent@example.com');
        $reviewsResponse->assertDontSee('Secret publication conditions text.');

        $homeResponse = $this->get(route('home.index'));
        $homeResponse->assertOk();
        $homeResponse->assertSee(self::DISCLOSURE_TEXT);
        $homeResponse->assertDontSee('Secret Consent Full Name');
        $homeResponse->assertDontSee('secret-consent@example.com');
        $homeResponse->assertDontSee('Secret publication conditions text.');
    }

    // -----------------------------------------------------------------------
    // 9, 10. Home controller explicit projection contains marker, not '*'
    // -----------------------------------------------------------------------

    public function test_home_controller_query_delivers_marker_to_view_via_explicit_projection(): void
    {
        $this->makeReview('Projection Author', [
            'content' => 'PROJECTION-MARKER-10',
            'is_moderator_edited' => true,
        ]);

        $response = $this->get(route('home.index'));

        $response->assertOk();
        $response->assertSee(self::DISCLOSURE_TEXT);

        $homeController = file_get_contents(app_path('Http/Controllers/Home/IndexController.php'));
        $this->assertMatchesRegularExpression(
            "/Reviews::select\\([^)]*'is_moderator_edited'[^)]*\\)/",
            $homeController
        );
        $this->assertStringNotContainsString("Reviews::select('*')", $homeController);
        $this->assertDoesNotMatchRegularExpression('/Reviews::(all|get)\\(\\)/', $homeController);
    }

    // -----------------------------------------------------------------------
    // 11. XSS escaping remains intact with the disclosure present
    // -----------------------------------------------------------------------

    public function test_disclosure_presence_does_not_break_name_and_content_escaping(): void
    {
        $maliciousName = '<script>alert("xss-disclosure-name")</script>';
        $maliciousContentBody = '<img src=x onerror=alert("xss-disclosure-content")>';
        $maliciousContent = $maliciousContentBody . ' MARKER-11';

        $this->makeReview($maliciousName, [
            'content' => $maliciousContent,
            'is_moderator_edited' => true,
        ]);

        $reviewsResponse = $this->get(route('review.index'));
        $reviewsResponse->assertOk();
        $reviewsResponse->assertSee(self::DISCLOSURE_TEXT);
        $reviewsResponse->assertDontSee($maliciousName, false);
        $reviewsResponse->assertDontSee($maliciousContentBody, false);
        $reviewsResponse->assertSee(e($maliciousName), false);
        $reviewsResponse->assertSee(e($maliciousContentBody), false);

        $homeResponse = $this->get(route('home.index'));
        $homeResponse->assertOk();
        $homeResponse->assertSee(self::DISCLOSURE_TEXT);
        $homeResponse->assertDontSee($maliciousName, false);
        $homeResponse->assertDontSee($maliciousContentBody, false);
        $homeResponse->assertSee(e($maliciousName), false);
        $homeResponse->assertSee(e($maliciousContentBody), false);
    }

    // -----------------------------------------------------------------------
    // 12. Unpublished reviews cannot expose the disclosure
    // -----------------------------------------------------------------------

    public function test_unpublished_edited_review_does_not_expose_disclosure(): void
    {
        $this->makeReview('Unpublished Edited Author', [
            'content' => 'UNPUBLISHED-EDITED-MARKER-12',
            'is_moderator_edited' => true,
            'is_published' => false,
        ]);

        $reviewsResponse = $this->get(route('review.index'));
        $reviewsResponse->assertOk();
        $reviewsResponse->assertDontSee('UNPUBLISHED-EDITED-MARKER-12');
        $reviewsResponse->assertDontSee(self::DISCLOSURE_TEXT);

        $homeResponse = $this->get(route('home.index'));
        $homeResponse->assertOk();
        $homeResponse->assertDontSee('UNPUBLISHED-EDITED-MARKER-12');
        $homeResponse->assertDontSee(self::DISCLOSURE_TEXT);
    }

    // -----------------------------------------------------------------------
    // 13. Soft-deleted reviews cannot expose the disclosure
    // -----------------------------------------------------------------------

    public function test_soft_deleted_edited_review_does_not_expose_disclosure(): void
    {
        $review = $this->makeReview('Soft Deleted Edited Author', [
            'content' => 'SOFT-DELETED-EDITED-MARKER-13',
            'is_moderator_edited' => true,
        ]);
        $review->delete();

        $reviewsResponse = $this->get(route('review.index'));
        $reviewsResponse->assertOk();
        $reviewsResponse->assertDontSee('SOFT-DELETED-EDITED-MARKER-13');
        $reviewsResponse->assertDontSee(self::DISCLOSURE_TEXT);

        $homeResponse = $this->get(route('home.index'));
        $homeResponse->assertOk();
        $homeResponse->assertDontSee('SOFT-DELETED-EDITED-MARKER-13');
        $homeResponse->assertDontSee(self::DISCLOSURE_TEXT);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeReview(string $name, array $overrides = []): Reviews
    {
        return Reviews::create(array_merge([
            'name' => $name,
            'title' => null,
            'content' => 'Default disclosure test content.',
            'image' => null,
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Isolates the single `/reviews` row (`<div class="row mb-4">`) that
     * contains the given marker, so isolation assertions inspect exactly
     * one review item instead of a fixed-size, potentially overlapping
     * character window.
     */
    private function extractReviewsRowFragment(string $html, string $marker): string
    {
        return $this->extractAncestorFragment($html, $marker, ['row', 'mb-4']);
    }

    /**
     * Isolates the single homepage review card
     * (`<div class="card h-100 d-flex flex-column">`) that contains the
     * given marker, so isolation assertions inspect exactly one review
     * item instead of a fixed-size, potentially overlapping character
     * window.
     */
    private function extractHomeCardFragment(string $html, string $marker): string
    {
        return $this->extractAncestorFragment($html, $marker, ['card', 'h-100', 'd-flex', 'flex-column']);
    }

    /**
     * Parses the response HTML with DOMDocument, locates the text node
     * containing the marker, and walks up the DOM to the nearest ancestor
     * element whose class list contains every required class TOKEN (not a
     * substring match, so e.g. "card" never matches "card-body"). Returns
     * that single ancestor element serialized back to HTML.
     */
    private function extractAncestorFragment(string $html, string $marker, array $requiredClassTokens): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $textNodes = $xpath->query('//text()[contains(., ' . $this->xpathLiteral($marker) . ')]');
        $this->assertNotFalse($textNodes, 'DOM text-node query for the marker failed.');
        $this->assertGreaterThan(0, $textNodes->length, "Marker {$marker} was not found in the parsed DOM.");

        $node = $textNodes->item(0);

        while ($node !== null) {
            if ($node instanceof \DOMElement) {
                $classTokens = preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [];
                $hasAllTokens = empty(array_diff($requiredClassTokens, $classTokens));

                if ($hasAllTokens) {
                    return $dom->saveHTML($node);
                }
            }

            $node = $node->parentNode;
        }

        $this->fail(sprintf(
            'Could not find an ancestor element with class tokens [%s] containing marker %s.',
            implode(' ', $requiredClassTokens),
            $marker
        ));
    }

    /**
     * Builds a safe XPath string literal for a value that is guaranteed
     * (by the fixed test markers used in this file) not to contain a
     * single quote.
     */
    private function xpathLiteral(string $value): string
    {
        $this->assertStringNotContainsString("'", $value, 'Test markers must not contain single quotes.');

        return "'" . $value . "'";
    }
}
