<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Stage 13: Reviews consent legal pages slice (C2).
 *
 * Проверяет только два новых анонимных информационных маршрута —
 * согласие на обработку персональных данных при направлении отзыва и
 * согласие на публикацию отзыва и имени. Не проверяет форму отзывов,
 * контроллер, валидацию или запись consent evidence — это остаётся в
 * текущем виде и будет интегрировано в C3.
 */
class PublicReviewConsentLegalPagesTest extends TestCase
{
    /**
     * Blade wraps long approved-legal-text sentences across source lines,
     * which is harmless for rendered HTML but breaks assertSee() calls that
     * expect a literal contiguous substring. This strips tags and collapses
     * whitespace so visitor-visible-text assertions are robust to that
     * formatting, without touching the underlying legal wording.
     */
    private function normalizedVisibleText($response): string
    {
        $text = html_entity_decode(strip_tags($response->getContent()), ENT_QUOTES, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    // -----------------------------------------------------------------------
    // A. Routes are registered as GET with the exact approved names
    // -----------------------------------------------------------------------

    public function test_review_personal_data_consent_route_is_registered_as_get_with_approved_name(): void
    {
        $this->assertTrue(Route::has('review_personal_data_consent.info'));

        $route = collect(Route::getRoutes())->first(
            fn ($r) => $r->getName() === 'review_personal_data_consent.info'
        );

        $this->assertNotNull($route);
        $this->assertContains('GET', $route->methods());
        $this->assertSame('reviews/personal-data-consent', $route->uri());
    }

    public function test_review_publication_consent_route_is_registered_as_get_with_approved_name(): void
    {
        $this->assertTrue(Route::has('review_publication_consent.info'));

        $route = collect(Route::getRoutes())->first(
            fn ($r) => $r->getName() === 'review_publication_consent.info'
        );

        $this->assertNotNull($route);
        $this->assertContains('GET', $route->methods());
        $this->assertSame('reviews/publication-consent', $route->uri());
    }

    // -----------------------------------------------------------------------
    // B. Both pages are reachable anonymously
    // -----------------------------------------------------------------------

    public function test_review_personal_data_consent_page_is_reachable_anonymously(): void
    {
        $response = $this->get(route('review_personal_data_consent.info'));

        $response->assertOk();
    }

    public function test_review_publication_consent_page_is_reachable_anonymously(): void
    {
        $response = $this->get(route('review_publication_consent.info'));

        $response->assertOk();
    }

    // -----------------------------------------------------------------------
    // C. Personal-data consent page: approved heading
    // -----------------------------------------------------------------------

    public function test_review_personal_data_consent_page_contains_approved_heading(): void
    {
        $response = $this->get(route('review_personal_data_consent.info'));

        $response->assertSee('Согласие на обработку персональных данных при направлении отзыва');
    }

    // -----------------------------------------------------------------------
    // D. Personal-data consent page: operator identity, scope, purposes
    // -----------------------------------------------------------------------

    public function test_review_personal_data_consent_page_contains_operator_identity_and_address(): void
    {
        $response = $this->get(route('review_personal_data_consent.info'));

        $response->assertSee('ООО «Авилона»', false);
        $response->assertSee('7805502454');
        $response->assertSee('1097847289803');
        $response->assertSee('Звенигородская, д. 22', false);
    }

    public function test_review_personal_data_consent_page_describes_collected_data_and_purposes(): void
    {
        $response = $this->get(route('review_personal_data_consent.info'));

        $response->assertSee('фамилия, имя, отчество', false);
        $response->assertSee('адрес электронной почты', false);
        $response->assertSee('имя, указанное', false);
        $response->assertSee('текст отзыва', false);
        $response->assertSee('условия или запреты для', false);
        $response->assertSee('модерация', false);
        $response->assertSee('подготовка отзыва к возможной публикации', false);
    }

    // -----------------------------------------------------------------------
    // E. Personal-data consent page: distribution boundary and withdrawal
    // -----------------------------------------------------------------------

    public function test_review_personal_data_consent_page_explains_it_does_not_authorize_publication(): void
    {
        $response = $this->get(route('review_personal_data_consent.info'));

        $visibleText = $this->normalizedVisibleText($response);

        $this->assertStringContainsString('не разрешает публичное распространение персональных', $visibleText);
        $this->assertStringContainsString('отдельного согласия на обработку персональных данных, разрешённых для распространения', $visibleText);
    }

    public function test_review_personal_data_consent_page_states_consent_name_and_email_not_published(): void
    {
        $response = $this->get(route('review_personal_data_consent.info'));

        $visibleText = $this->normalizedVisibleText($response);

        $this->assertStringContainsString('не публикуются вместе с отзывом', $visibleText);
        $this->assertStringContainsString('не используются для рекламных рассылок', $visibleText);
    }

    public function test_review_personal_data_consent_page_contains_withdrawal_email(): void
    {
        $response = $this->get(route('review_personal_data_consent.info'));

        $response->assertSee('avilonatur@bk.ru');
    }

    // -----------------------------------------------------------------------
    // F. Personal-data consent page: policy PDF link
    // -----------------------------------------------------------------------

    public function test_review_personal_data_consent_page_links_to_processing_policy_pdf(): void
    {
        $response = $this->get(route('review_personal_data_consent.info'));

        $response->assertSee('Policy_regarding_the_protection_and_processing_of_personal_data.pdf');
        $response->assertSee('target="_blank" rel="noopener"', false);
    }

    // -----------------------------------------------------------------------
    // G. Publication consent page: approved heading
    // -----------------------------------------------------------------------

    public function test_review_publication_consent_page_contains_approved_heading(): void
    {
        $response = $this->get(route('review_publication_consent.info'));

        $response->assertSee('Согласие на публикацию отзыва и имени');
    }

    // -----------------------------------------------------------------------
    // H. Publication consent page: operator identity, URLs, purpose
    // -----------------------------------------------------------------------

    public function test_review_publication_consent_page_contains_operator_identity(): void
    {
        $response = $this->get(route('review_publication_consent.info'));

        $response->assertSee('ООО «Авилона»', false);
        $response->assertSee('7805502454');
        $response->assertSee('1097847289803');
        $response->assertSee('Звенигородская, д. 22', false);
    }

    public function test_review_publication_consent_page_contains_publication_resource_urls(): void
    {
        $response = $this->get(route('review_publication_consent.info'));

        $response->assertSee('https://avilona.ru/', false);
        $response->assertSee('https://avilona.ru/reviews', false);
    }

    public function test_review_publication_consent_page_contains_publication_purpose(): void
    {
        $response = $this->get(route('review_publication_consent.info'));

        $response->assertSee('публикация отзыва клиента туристической фирмы', false);
    }

    // -----------------------------------------------------------------------
    // I. Publication consent page: explicit permitted public scope
    // -----------------------------------------------------------------------

    public function test_review_publication_consent_page_states_permitted_public_scope(): void
    {
        $response = $this->get(route('review_publication_consent.info'));

        $visibleText = $this->normalizedVisibleText($response);

        $this->assertStringContainsString('«Ваше имя»', $visibleText);
        $this->assertStringContainsString('текст моего отзыва', $visibleText);
    }

    // -----------------------------------------------------------------------
    // J. Publication consent page: excludes consent full name/email
    // -----------------------------------------------------------------------

    public function test_review_publication_consent_page_excludes_consent_full_name_and_email_from_publication(): void
    {
        $response = $this->get(route('review_publication_consent.info'));

        $response->assertSee('не разрешены мной для публикации', false);
    }

    // -----------------------------------------------------------------------
    // K. Publication consent page: moderation contract
    // -----------------------------------------------------------------------

    public function test_review_publication_consent_page_contains_moderation_contract(): void
    {
        $response = $this->get(route('review_publication_consent.info'));

        $response->assertSee('не публикуется автоматически', false);
        $response->assertSee('предварительно проходит модерацию', false);
        $response->assertSee('ненормативная лексика', false);
        $response->assertSee('персональные данные', false);
        $response->assertSee('избыточные контактные данные', false);
        $response->assertSee('без изменения общего смысла отзыва', false);
        $response->assertSee('отредактирован модератором', false);
    }

    // -----------------------------------------------------------------------
    // L. Publication consent page: conditions/prohibitions contract
    // -----------------------------------------------------------------------

    public function test_review_publication_consent_page_contains_conditions_contract(): void
    {
        $response = $this->get(route('review_publication_consent.info'));

        $response->assertSee('условия или запреты для распространения', false);
        $response->assertSee('оставлено мной пустым', false);
        $response->assertSee('дополнительных', false);
    }

    // -----------------------------------------------------------------------
    // M. Publication consent page: withdrawal/termination language
    // -----------------------------------------------------------------------

    public function test_review_publication_consent_page_contains_termination_language_and_email(): void
    {
        $response = $this->get(route('review_publication_consent.info'));

        $visibleText = $this->normalizedVisibleText($response);

        $this->assertStringContainsString('прекращается при удалении отзыва', $visibleText);
        $this->assertStringContainsString('требования о прекращении распространения', $visibleText);
        $this->assertStringContainsString('Требование о прекращении распространения можно направить', $visibleText);
        $response->assertSee('avilonatur@bk.ru');
    }

    // -----------------------------------------------------------------------
    // N. Regression guard: no subject/title publication category
    // -----------------------------------------------------------------------

    public function test_review_publication_consent_page_does_not_introduce_subject_as_publication_category(): void
    {
        $response = $this->get(route('review_publication_consent.info'));

        $response->assertDontSee('тема отзыва', false);
        $response->assertDontSee('заголовок отзыва', false);
    }

    // -----------------------------------------------------------------------
    // O. Existing generic personal-data consent page is untouched
    // -----------------------------------------------------------------------

    public function test_generic_personal_data_consent_page_remains_reachable_with_its_own_name(): void
    {
        $this->assertTrue(Route::has('personal_data_consent.info'));

        $response = $this->get(route('personal_data_consent.info'));

        $response->assertOk();
    }

    // -----------------------------------------------------------------------
    // P. Existing public Reviews routes remain registered
    // -----------------------------------------------------------------------

    public function test_existing_reviews_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('review.index'));
        $this->assertTrue(Route::has('review_create.index'));

        $indexRoute = collect(Route::getRoutes())->first(
            fn ($r) => $r->getName() === 'review.index'
        );
        $createRoute = collect(Route::getRoutes())->first(
            fn ($r) => $r->getName() === 'review_create.index'
        );

        $this->assertNotNull($indexRoute);
        $this->assertContains('GET', $indexRoute->methods());
        $this->assertSame('reviews', $indexRoute->uri());

        $this->assertNotNull($createRoute);
        $this->assertContains('POST', $createRoute->methods());
        $this->assertSame('reviews/create', $createRoute->uri());
    }
}
