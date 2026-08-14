<?php

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use App\Mail\HomeFormMail;
use App\Mail\UserFormMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Stage 13: Home + Contacts — separate personal-data consent.
 *
 * Проверяет, что User Agreement (agree) и согласие на обработку персональных
 * данных (personal_data_consent) — два независимых обязательных чекбокса на
 * формах Home и Contacts, что оба валидируются как 'accepted' (а не просто
 * "поле присутствует"), и что появилась отдельная публичная страница
 * /personal-data-consent с согласованным визитор-facing текстом.
 */
class PublicPersonalDataConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Validator::extend('captcha', function () {
            return true;
        });
    }

    private function validPayload(): array
    {
        return [
            'name'    => 'Test User',
            'email'   => 'submitter@example.com',
            'subject' => 'Hello',
            'message' => str_repeat('A', 60),
            'captcha' => 'anything',
            'agree'   => '1',
            'personal_data_consent' => '1',
        ];
    }

    // -----------------------------------------------------------------------
    // Standalone consent page
    // -----------------------------------------------------------------------

    public function test_personal_data_consent_page_is_reachable_anonymously(): void
    {
        $response = $this->get(route('personal_data_consent.info'));

        $response->assertOk();
    }

    public function test_personal_data_consent_page_contains_approved_heading(): void
    {
        $response = $this->get(route('personal_data_consent.info'));

        $response->assertSee('Согласие на обработку персональных данных');
    }

    public function test_personal_data_consent_page_contains_operator_identity(): void
    {
        $response = $this->get(route('personal_data_consent.info'));

        $response->assertSee('ООО «Авилона»', false);
        $response->assertSee('7805502454');
        $response->assertSee('1097847289803');
    }

    public function test_personal_data_consent_page_contains_legal_address(): void
    {
        $response = $this->get(route('personal_data_consent.info'));

        $response->assertSee('Звенигородская, д. 22');
    }

    public function test_personal_data_consent_page_contains_withdrawal_email(): void
    {
        $response = $this->get(route('personal_data_consent.info'));

        $response->assertSee('avilonatur@bk.ru');
    }

    public function test_personal_data_consent_page_links_to_processing_policy_pdf(): void
    {
        $response = $this->get(route('personal_data_consent.info'));

        $response->assertSee('Policy_regarding_the_protection_and_processing_of_personal_data.pdf');
    }

    // -----------------------------------------------------------------------
    // Home / Contacts render two separate consent fields
    // -----------------------------------------------------------------------

    public function test_home_page_contains_two_separate_consent_fields(): void
    {
        $response = $this->get(route('home.index'));

        $response->assertOk();
        $response->assertSee('name="agree"', false);
        $response->assertSee('name="personal_data_consent"', false);
    }

    public function test_contacts_page_contains_two_separate_consent_fields(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertOk();
        $response->assertSee('name="agree"', false);
        $response->assertSee('name="personal_data_consent"', false);
    }

    public function test_home_page_contains_user_agreement_link_for_checkbox_one(): void
    {
        $response = $this->get(route('home.index'));

        $response->assertSee(asset('/documents/User_Agreement.pdf'), false);
    }

    public function test_contacts_page_contains_user_agreement_link_for_checkbox_one(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertSee(asset('/documents/User_Agreement.pdf'), false);
    }

    public function test_home_page_contains_personal_data_consent_page_link_for_checkbox_two(): void
    {
        $response = $this->get(route('home.index'));

        $response->assertSee(route('personal_data_consent.info'), false);
    }

    public function test_contacts_page_contains_personal_data_consent_page_link_for_checkbox_two(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertSee(route('personal_data_consent.info'), false);
    }

    public function test_home_page_does_not_contain_old_bundled_consent_statement(): void
    {
        $response = $this->get(route('home.index'));

        $response->assertDontSee(
            'принимаю условия Пользовательского соглашения</a> и даю своё согласие на',
            false
        );
    }

    public function test_contacts_page_does_not_contain_old_bundled_consent_statement(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertDontSee(
            'принимаю условия Пользовательского соглашения</a> и даю своё согласие на',
            false
        );
    }

    // -----------------------------------------------------------------------
    // Validation: independent 'accepted' semantics
    // -----------------------------------------------------------------------

    public function test_home_post_fails_when_agree_missing(): void
    {
        Mail::fake();

        $payload = $this->validPayload();
        unset($payload['agree']);

        $this->post(route('contact.send_home'), $payload)
            ->assertSessionHasErrors('agree');

        Mail::assertNothingSent();
    }

    public function test_home_post_fails_when_personal_data_consent_missing(): void
    {
        Mail::fake();

        $payload = $this->validPayload();
        unset($payload['personal_data_consent']);

        $this->post(route('contact.send_home'), $payload)
            ->assertSessionHasErrors('personal_data_consent');

        Mail::assertNothingSent();
    }

    public function test_contacts_post_fails_when_agree_missing(): void
    {
        Mail::fake();

        $payload = $this->validPayload();
        unset($payload['agree']);

        $this->post(route('contact.send_contact'), $payload)
            ->assertSessionHasErrors('agree');

        Mail::assertNothingSent();
    }

    public function test_contacts_post_fails_when_personal_data_consent_missing(): void
    {
        Mail::fake();

        $payload = $this->validPayload();
        unset($payload['personal_data_consent']);

        $this->post(route('contact.send_contact'), $payload)
            ->assertSessionHasErrors('personal_data_consent');

        Mail::assertNothingSent();
    }

    public function test_agree_zero_is_rejected_as_not_accepted(): void
    {
        Mail::fake();

        $payload = $this->validPayload();
        $payload['agree'] = '0';

        $this->post(route('contact.send_home'), $payload)
            ->assertSessionHasErrors('agree');

        Mail::assertNothingSent();
    }

    public function test_personal_data_consent_zero_is_rejected_as_not_accepted(): void
    {
        Mail::fake();

        $payload = $this->validPayload();
        $payload['personal_data_consent'] = '0';

        $this->post(route('contact.send_home'), $payload)
            ->assertSessionHasErrors('personal_data_consent');

        Mail::assertNothingSent();
    }

    // -----------------------------------------------------------------------
    // Valid payload with both fields still goes through the mail flow
    // -----------------------------------------------------------------------

    public function test_home_valid_payload_with_both_consents_sends_mail(): void
    {
        Mail::fake();

        $payload = $this->validPayload();

        $this->post(route('contact.send_home'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(HomeFormMail::class);
        Mail::assertSent(UserFormMail::class);
    }

    public function test_contacts_valid_payload_with_both_consents_sends_mail(): void
    {
        Mail::fake();

        $payload = $this->validPayload();

        $this->post(route('contact.send_contact'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(ContactFormMail::class);
        Mail::assertSent(UserFormMail::class);
    }
}
