<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRegistrationConsent;
use App\Providers\RouteServiceProvider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationConsentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'consent-test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_agreement_accepted' => '1',
            'personal_data_consent_accepted' => '1',
        ], $overrides);
    }

    private function expectedUserAgreementVersion(): string
    {
        return 'sha256:' . hash_file('sha256', public_path('documents/User_Agreement.pdf'));
    }

    private function expectedPersonalDataConsentVersion(): string
    {
        return 'sha256:' . hash_file(
            'sha256',
            resource_path('views/legal/registration-personal-data-consent.blade.php')
        );
    }

    // 1
    public function test_registration_page_renders_two_distinct_consent_checkboxes(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('name="user_agreement_accepted"', false);
        $response->assertSee('name="personal_data_consent_accepted"', false);
    }

    // Browser-QA regression: neither consent checkbox may carry the HTML
    // boolean `required` attribute, otherwise native browser validation
    // blocks the POST before Laravel's own validation/messages/old() are
    // ever reached.
    public function test_consent_checkboxes_have_no_client_side_required_attribute(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertDontSee('name="user_agreement_accepted" value="1" required', false);
        $response->assertDontSee('name="personal_data_consent_accepted" value="1" required', false);
    }

    // 2
    public function test_user_agreement_checkbox_links_to_the_pdf_document(): void
    {
        $response = $this->get('/register');

        $response->assertSee('/documents/User_Agreement.pdf', false);
    }

    // 3
    public function test_personal_data_checkbox_links_to_the_registration_consent_route(): void
    {
        $response = $this->get('/register');

        $response->assertSee(route('registration_personal_data_consent.info'), false);
    }

    // 4
    public function test_missing_user_agreement_acceptance_is_rejected_independently_and_creates_no_user(): void
    {
        $payload = $this->validPayload();
        unset($payload['user_agreement_accepted']);

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors('user_agreement_accepted');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    // 5
    public function test_missing_personal_data_consent_is_rejected_independently_and_creates_no_user(): void
    {
        $payload = $this->validPayload();
        unset($payload['personal_data_consent_accepted']);

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors('personal_data_consent_accepted');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    // 6
    public function test_false_value_for_either_consent_field_is_rejected(): void
    {
        $response = $this->post('/register', $this->validPayload([
            'user_agreement_accepted' => '0',
            'personal_data_consent_accepted' => '0',
        ]));

        $response->assertSessionHasErrors(['user_agreement_accepted', 'personal_data_consent_accepted']);
        $this->assertDatabaseCount('users', 0);
    }

    // 7
    public function test_custom_russian_validation_messages_are_user_readable(): void
    {
        $payload = $this->validPayload();
        unset($payload['user_agreement_accepted'], $payload['personal_data_consent_accepted']);

        $this->post('/register', $payload);

        $errors = session('errors');

        $this->assertSame(
            'Пожалуйста, примите условия Пользовательского соглашения',
            $errors->first('user_agreement_accepted')
        );
        $this->assertSame(
            'Пожалуйста, дайте согласие на обработку персональных данных',
            $errors->first('personal_data_consent_accepted')
        );
    }

    // 8, 9, 10, 11, 12
    public function test_successful_registration_creates_one_user_and_one_consent_record_with_matching_fingerprints(): void
    {
        $response = $this->post('/register', $this->validPayload());

        $response->assertRedirect(RouteServiceProvider::HOME);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_registration_consents', 1);

        $user = User::query()->sole();
        $consent = UserRegistrationConsent::query()->sole();

        $this->assertSame($user->id, $consent->user_id);
        $this->assertNotNull($consent->user_agreement_accepted_at);
        $this->assertNotNull($consent->personal_data_consent_accepted_at);
        $this->assertTrue($consent->user_agreement_accepted_at->equalTo($consent->personal_data_consent_accepted_at));

        $this->assertSame($this->expectedUserAgreementVersion(), $consent->user_agreement_version);
        $this->assertSame($this->expectedPersonalDataConsentVersion(), $consent->personal_data_consent_version);
    }

    // 13, 14
    public function test_user_and_consent_relations_resolve_correctly(): void
    {
        $this->post('/register', $this->validPayload());

        $user = User::query()->sole();
        $consent = UserRegistrationConsent::query()->sole();

        $this->assertInstanceOf(UserRegistrationConsent::class, $user->registrationConsent);
        $this->assertSame($consent->id, $user->registrationConsent->id);

        $this->assertInstanceOf(User::class, $consent->user);
        $this->assertSame($user->id, $consent->user->id);
    }

    // 15
    public function test_schema_contains_no_forbidden_metadata_columns(): void
    {
        $columns = Schema::getColumnListing('user_registration_consents');

        $forbidden = [
            'publication_scope',
            'publication_conditions',
            'publication_conditions_satisfied_at',
            'review_payload_sha256',
            'withdrawn_at',
            'evidence_id',
            'ip_address',
            'user_agent',
            'device_fingerprint',
            'session_id',
            'geolocation',
            'referrer',
            'advertising_id',
            'marketing_opt_in',
        ];

        foreach ($forbidden as $column) {
            $this->assertNotContains($column, $columns);
        }
    }

    // 16
    public function test_forged_client_supplied_evidence_values_are_ignored(): void
    {
        $this->post('/register', $this->validPayload([
            'user_agreement_version' => 'forged-version',
            'personal_data_consent_version' => 'forged-version',
            'user_agreement_accepted_at' => '2000-01-01 00:00:00',
            'personal_data_consent_accepted_at' => '2000-01-01 00:00:00',
        ]));

        $consent = UserRegistrationConsent::query()->sole();

        $this->assertSame($this->expectedUserAgreementVersion(), $consent->user_agreement_version);
        $this->assertSame($this->expectedPersonalDataConsentVersion(), $consent->personal_data_consent_version);
        $this->assertNotSame('2000-01-01 00:00:00', $consent->user_agreement_accepted_at->toDateTimeString());
        $this->assertNotSame('2000-01-01 00:00:00', $consent->personal_data_consent_accepted_at->toDateTimeString());
    }

    // 17
    public function test_duplicate_evidence_for_the_same_user_is_rejected_by_the_db_unique_constraint(): void
    {
        $user = User::factory()->create();

        UserRegistrationConsent::create([
            'user_id' => $user->id,
            'user_agreement_accepted_at' => now(),
            'user_agreement_version' => $this->expectedUserAgreementVersion(),
            'personal_data_consent_accepted_at' => now(),
            'personal_data_consent_version' => $this->expectedPersonalDataConsentVersion(),
        ]);

        $this->expectException(QueryException::class);

        UserRegistrationConsent::create([
            'user_id' => $user->id,
            'user_agreement_accepted_at' => now(),
            'user_agreement_version' => $this->expectedUserAgreementVersion(),
            'personal_data_consent_accepted_at' => now(),
            'personal_data_consent_version' => $this->expectedPersonalDataConsentVersion(),
        ]);
    }

    // 18
    public function test_successful_registration_authenticates_and_redirects_home(): void
    {
        $response = $this->post('/register', $this->validPayload());

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    // 19
    public function test_successful_registration_assigns_tourist_role(): void
    {
        $this->post('/register', $this->validPayload());

        $user = User::query()->sole();

        $this->assertTrue($user->hasRole(Role::TOURIST));
    }

    // 20
    public function test_duplicate_email_and_password_confirmation_mismatch_remain_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('/register', $this->validPayload(['email' => 'taken@example.com']));
        $response->assertSessionHasErrors('email');

        $response = $this->post('/register', $this->validPayload(['password_confirmation' => 'mismatched']));
        $response->assertSessionHasErrors('password');
    }

    // Atomic rollback
    public function test_evidence_persistence_failure_rolls_back_the_entire_registration(): void
    {
        $this->withoutExceptionHandling();

        Event::listen(
            'eloquent.created: ' . UserRegistrationConsent::class,
            fn (): never => throw new \RuntimeException('forced failure')
        );

        $before = [
            'users' => User::query()->count(),
            'role_user' => DB::table('role_user')->count(),
            'user_registration_consents' => UserRegistrationConsent::query()->count(),
        ];

        try {
            $this->post('/register', $this->validPayload());
            $this->fail('Expected the forced failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure', $e->getMessage());
        }

        $after = [
            'users' => User::query()->count(),
            'role_user' => DB::table('role_user')->count(),
            'user_registration_consents' => UserRegistrationConsent::query()->count(),
        ];

        $this->assertSame($before, $after);
        $this->assertDatabaseMissing('users', ['email' => 'consent-test@example.com']);
        $this->assertGuest();
    }
}
