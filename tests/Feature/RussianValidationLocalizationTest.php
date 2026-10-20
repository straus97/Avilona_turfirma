<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * AO-FIX-05 (AO-10) — русские сообщения валидации.
 *
 * Активная локаль приложения — `ru` (fallback `en`). Раньше lang/ru/validation.php
 * был английским файлом с четырьмя переведёнными строками, из-за чего
 * пользователи видели «The электронная почта must be …», «message должен быть …»
 * и даже сырой ключ «validation.captcha». Тест фиксирует:
 *   - полноту и структурное соответствие русского каталога английскому;
 *   - сохранность подстановок (:min, :max, :value, :other, :date, :values, …);
 *   - отсутствие английского текста в каталоге и в реальных ответах форм;
 *   - контракт именованного error bag `updatePassword` (AO-FIX-02).
 *
 * Формулировки намеренно проверяются по смыслу (подпись поля, число из правила,
 * отсутствие латиницы), а не по пунктуации целиком.
 */
class RussianValidationLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'Old-Passw0rd-AO10';
    private const NEW_PASSWORD = 'New-Passw0rd-AO10';

    /** Латиница, допустимая в русском тексте: названия форматов и протоколов. */
    private const ALLOWED_LATIN = ['JSON', 'IPv4', 'IPv6', 'IP', 'MAC', 'ULID', 'UUID', 'Slug', 'Email'];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        foreach ([Role::ADMIN, Role::MANAGER, Role::TOURIST] as $roleName) {
            Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['description' => Role::availableRoles()[$roleName] ?? $roleName]
            );
        }
    }

    // ------------------------------------------------------------------
    // 1. Locale strategy and catalogue integrity
    // ------------------------------------------------------------------

    public function test_application_locale_strategy_is_unchanged(): void
    {
        $this->assertSame('ru', config('app.locale'));
        $this->assertSame('en', config('app.fallback_locale'));
        $this->assertSame('ru', app()->getLocale());
    }

    public function test_russian_catalogue_defines_every_english_rule_plus_only_the_captcha_rule(): void
    {
        $en = $this->flattenKeys($this->catalogue('en'));
        $ru = $this->flattenKeys($this->catalogue('ru'));

        $this->assertSame([], array_values(array_diff($en, $ru)), 'Russian catalogue is missing rules defined in English.');
        // «captcha» регистрирует пакет mews/captcha; в английском каталоге строки для него нет.
        $this->assertSame(['captcha'], array_values(array_diff($ru, $en)));
    }

    public function test_every_rule_message_is_russian_without_stray_latin_text(): void
    {
        foreach ($this->flatten($this->rules('ru')) as $key => $message) {
            $this->assertIsString($message, $key);
            $this->assertNotSame('', trim($message), "validation.{$key} is empty");

            $this->assertSame(
                [],
                $this->strayLatin($message),
                "validation.{$key} still contains English text: {$message}"
            );
            $this->assertMatchesRegularExpression('/\p{Cyrillic}/u', $message, "validation.{$key} is not Russian: {$message}");
        }
    }

    public function test_custom_messages_and_attribute_labels_are_russian(): void
    {
        $ru = $this->catalogue('ru');

        foreach ($this->flatten($ru['custom'] ?? []) as $key => $message) {
            $this->assertSame([], $this->strayLatin($message), "validation.custom.{$key}: {$message}");
        }
        foreach ($ru['attributes'] as $field => $label) {
            $this->assertMatchesRegularExpression('/\p{Cyrillic}|^Slug$/u', $label, "attribute {$field}");
            $this->assertSame([], $this->strayLatin($label), "attribute {$field}: {$label}");
        }
    }

    public function test_placeholders_are_preserved_and_none_are_invented(): void
    {
        $en = $this->flatten($this->rules('en'));
        $ru = $this->flatten($this->rules('ru'));

        // :attribute намеренно не обязателен (например, «пароль встречается в утечках»);
        // все остальные подстановки английской строки обязаны остаться в русской.
        foreach ($en as $key => $enMessage) {
            $enPlaceholders = array_diff($this->placeholders($enMessage), ['attribute']);
            $ruPlaceholders = $this->placeholders($ru[$key]);

            $this->assertSame(
                [],
                array_values(array_diff($enPlaceholders, $ruPlaceholders)),
                "validation.{$key} lost a placeholder"
            );
            $this->assertSame(
                [],
                array_values(array_diff($ruPlaceholders, $this->placeholders($enMessage))),
                "validation.{$key} introduced a placeholder Laravel does not supply"
            );
        }
    }

    public function test_english_catalogue_is_untouched(): void
    {
        $this->assertSame('The :attribute must be a valid email address.', Lang::get('validation.email', [], 'en'));
        $this->assertSame('The :attribute field is required.', Lang::get('validation.required', [], 'en'));
    }

    // ------------------------------------------------------------------
    // 2. Real validator output for representative rules
    // ------------------------------------------------------------------

    public function test_representative_rules_render_in_russian_with_interpolated_values(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $cases = [
            'required' => [['name' => ''], ['name' => 'required'], 'name', ['Имя']],
            'email' => [['email' => 'abc'], ['email' => 'email'], 'email', ['Электронная почта']],
            'min string' => [['message' => 'short'], ['message' => 'required|min:50'], 'message', ['Сообщение', '50']],
            'max string' => [['name' => str_repeat('a', 300)], ['name' => 'max:255'], 'name', ['Имя', '255']],
            'integer' => [['nights' => 'x'], ['nights' => 'integer'], 'nights', ['ночей']],
            'min numeric' => [['nights' => 0], ['nights' => 'integer|min:1'], 'nights', ['1']],
            'max numeric' => [['adults' => 99], ['adults' => 'integer|max:10'], 'adults', ['10']],
            'numeric' => [['total_price' => 'abc'], ['total_price' => 'numeric'], 'total_price', ['Итоговая стоимость']],
            'date' => [['birth_date' => 'zzz'], ['birth_date' => 'date'], 'birth_date', ['Дата рождения']],
            'after_or_equal field' => [
                ['start_date' => '2030-01-02', 'start_date_end' => '2030-01-01'],
                ['start_date_end' => 'date|after_or_equal:start_date'],
                'start_date_end', ['Дата вылета (по)', 'Дата вылета (с)'],
            ],
            'unique' => [['email' => 'taken@example.test'], ['email' => 'unique:users,email'], 'email', ['Электронная почта']],
            'exists' => [['role' => 'nope'], ['role' => 'exists:roles,name'], 'role', ['Роль']],
            'in' => [['gender' => 'x'], ['gender' => 'in:male,female'], 'gender', ['Пол']],
            'accepted' => [['personal_data_consent' => ''], ['personal_data_consent' => 'accepted'], 'personal_data_consent', ['Согласие']],
            'url' => [['image' => 'nope'], ['image' => 'url'], 'image', ['Изображение']],
            'boolean' => [['is_published' => 'x'], ['is_published' => 'boolean'], 'is_published', ['Публикация']],
            'array' => [['children_ages' => 'x'], ['children_ages' => 'array'], 'children_ages', ['Возраст детей']],
            'wildcard attribute' => [['children_ages' => [99]], ['children_ages.*' => 'integer|max:17'], 'children_ages.0', ['Возраст ребёнка', '17']],
            'string' => [['title' => ['a']], ['title' => 'string'], 'title', ['Заголовок']],
            'required_without' => [['message' => ''], ['message' => 'required_without:attachment'], 'message', ['Сообщение', 'Вложение']],
            'regex' => [['passport_number' => '!'], ['passport_number' => 'regex:/^[0-9]+$/'], 'passport_number', ['Номер паспорта']],
        ];

        foreach ($cases as $label => [$data, $rules, $field, $expectedFragments]) {
            $message = Validator::make($data, $rules)->errors()->first($field);

            $this->assertNotSame('', $message, "{$label}: rule did not fail");
            $this->assertRussianMessage($message, $label);
            foreach ($expectedFragments as $fragment) {
                $this->assertStringContainsString($fragment, $message, "{$label}: {$message}");
            }
        }
    }

    public function test_file_rules_render_in_russian(): void
    {
        $image = Validator::make(
            ['avatar' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
            ['avatar' => 'image']
        )->errors()->first('avatar');
        $mimes = Validator::make(
            ['file' => UploadedFile::fake()->create('a.exe', 10, 'application/x-msdownload')],
            ['file' => 'file|mimes:pdf,doc']
        )->errors()->first('file');
        $size = Validator::make(
            ['file' => UploadedFile::fake()->create('a.pdf', 20000, 'application/pdf')],
            ['file' => 'file|max:10240']
        )->errors()->first('file');

        $this->assertRussianMessage($image, 'image');
        $this->assertStringContainsString('Фотография', $image);

        $this->assertRussianMessage($mimes, 'mimes');
        $this->assertStringContainsString('pdf, doc', $mimes);

        $this->assertRussianMessage($size, 'file max');
        $this->assertStringContainsString('10240', $size);
    }

    public function test_third_party_captcha_rule_has_a_russian_message_instead_of_a_raw_key(): void
    {
        $message = Validator::make(['captcha' => 'wrong'], ['captcha' => 'required|captcha'])->errors()->first('captcha');

        $this->assertNotSame('validation.captcha', $message);
        $this->assertRussianMessage($message, 'captcha');
    }

    public function test_after_today_does_not_leak_the_english_word_today(): void
    {
        $message = Validator::make(['start_date' => '2000-01-01'], ['start_date' => 'date|after:today'])
            ->errors()->first('start_date');

        $this->assertRussianMessage($message, 'after:today');
        $this->assertStringNotContainsStringIgnoringCase('today', $message);
    }

    public function test_every_form_field_has_a_russian_attribute_label(): void
    {
        // Поля, которые валидируются формами кабинетов, бронирования, отзывов,
        // контактов и регистрации. Без подписи Laravel подставил бы «passport issued by».
        $fields = [
            'name', 'email', 'password', 'password_confirmation', 'current_password', 'phone', 'birth_date',
            'gender', 'address', 'passport_number', 'passport_issued_date', 'passport_issued_by', 'avatar',
            'document', 'document_type', 'file', 'attachment', 'image', 'title', 'content', 'slug', 'subject',
            'message', 'captcha', 'agree', 'personal_data_consent', 'user_agreement_accepted',
            'personal_data_consent_accepted', 'consent_full_name', 'consent_email', 'publication_conditions',
            'publication_conditions_satisfied', 'withdrawal_confirmed', 'is_published', 'role', 'status',
            'manager_id', 'assigned_to', 'booking_id', 'receiver_id', 'tour_id', 'client_id', 'client_name',
            'client_email', 'client_phone', 'departure_city', 'destination_country', 'destination_city',
            'start_date', 'start_date_end', 'nights', 'nights_max', 'adults', 'children', 'children_ages',
            'tourists_data', 'total_price', 'notes', 'manager_notes', 'close_reason',
        ];

        foreach ($fields as $field) {
            $message = Validator::make([$field => ''], [$field => 'required'])->errors()->first($field);

            $this->assertRussianMessage($message, $field);
            $this->assertStringNotContainsString(str_replace('_', ' ', $field), $message, "{$field}: raw identifier shown");
        }
    }

    // ------------------------------------------------------------------
    // 3. Tourist password form (AO-FIX-02 named bag contract)
    // ------------------------------------------------------------------

    public function test_tourist_wrong_current_password_error_is_russian_and_under_current_password(): void
    {
        $tourist = $this->makeUser(Role::TOURIST);

        $html = $this->submitTouristPasswordForm($tourist, [
            'current_password' => 'Wrong-Passw0rd-AO10',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $message = $this->fieldFeedback($html, 'current_password');
        $this->assertRussianMessage($message, 'current_password');
        $this->assertStringContainsString('пароль', mb_strtolower($message));
        $this->assertNull($this->fieldFeedback($html, 'password'));
        $this->assertPasswordUnchanged($tourist);
        $this->assertSecretsAbsent($html, ['Wrong-Passw0rd-AO10']);
    }

    public function test_tourist_confirmation_mismatch_error_is_russian_and_under_new_password(): void
    {
        $tourist = $this->makeUser(Role::TOURIST);

        $html = $this->submitTouristPasswordForm($tourist, [
            'current_password' => self::OLD_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'Different-Passw0rd-AO10',
        ]);

        $message = $this->fieldFeedback($html, 'password');
        $this->assertRussianMessage($message, 'confirmed');
        $this->assertStringContainsString('Пароли не совпадают', $message);
        $this->assertNull($this->fieldFeedback($html, 'current_password'));
        $this->assertPasswordUnchanged($tourist);
        $this->assertSecretsAbsent($html, ['Different-Passw0rd-AO10']);
    }

    public function test_tourist_too_short_new_password_error_is_russian_and_states_the_minimum(): void
    {
        $tourist = $this->makeUser(Role::TOURIST);

        $html = $this->submitTouristPasswordForm($tourist, [
            'current_password' => self::OLD_PASSWORD,
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ]);

        $message = $this->fieldFeedback($html, 'password');
        $this->assertRussianMessage($message, 'min');
        $this->assertStringContainsString('Пароль', $message);
        $this->assertStringContainsString('8', $message);
        $this->assertNull($this->fieldFeedback($html, 'current_password'));
        $this->assertPasswordUnchanged($tourist);
        $this->assertSecretsAbsent($html, ['short1']);
    }

    public function test_tourist_empty_password_form_reports_required_fields_in_russian(): void
    {
        $tourist = $this->makeUser(Role::TOURIST);

        $html = $this->submitTouristPasswordForm($tourist, []);

        $this->assertRussianMessage((string) $this->fieldFeedback($html, 'current_password'), 'required current');
        $this->assertRussianMessage((string) $this->fieldFeedback($html, 'password'), 'required new');
        $this->assertStringContainsString('Текущий пароль', (string) $this->fieldFeedback($html, 'current_password'));
        $this->assertPasswordUnchanged($tourist);
    }

    public function test_tourist_password_errors_stay_in_the_named_bag_and_do_not_leak_to_other_forms(): void
    {
        $tourist = $this->makeUser(Role::TOURIST);

        $html = $this->submitTouristPasswordForm($tourist, [
            'current_password' => 'Wrong-Passw0rd-AO10',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $outside = $this->xpath($html)->query(
            '//form[not(@id="passwordForm")]//*[contains(@class,"is-invalid") or contains(@class,"invalid-feedback")]'
        );
        $this->assertSame(0, $outside->length);

        $this->actingAs($tourist)
            ->from(route('cabinet.settings'))
            ->put(route('password.update'), [
                'current_password' => 'Wrong-Passw0rd-AO10',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertSessionHasErrorsIn('updatePassword', ['current_password']);
        $this->assertFalse(session('errors')->getBag('default')->any());
    }

    public function test_tourist_successful_password_change_is_unchanged(): void
    {
        $tourist = $this->makeUser(Role::TOURIST);

        $html = $this->submitTouristPasswordForm($tourist, [
            'current_password' => self::OLD_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $tourist->fresh()->password));
        $this->assertStringContainsString('Пароль успешно изменен!', $html);
        $this->assertSecretsAbsent($html);
    }

    // ------------------------------------------------------------------
    // 4. Representative Tourist / Manager / public flows
    // ------------------------------------------------------------------

    public function test_tourist_profile_validation_errors_are_russian_and_html_escaped(): void
    {
        $tourist = $this->makeUser(Role::TOURIST);
        $payload = '<script>alert("ao10")</script>';

        $response = $this->actingAs($tourist)
            ->from(route('cabinet.profile'))
            ->patch(route('cabinet.profile.update'), [
                'name' => '',
                'email' => 'not-an-email',
                'birth_date' => 'zzz',
                'phone' => $payload . str_repeat('1', 30),
            ])
            ->assertRedirect(route('cabinet.profile'))
            ->assertSessionHasErrors(['name', 'email', 'birth_date', 'phone']);

        foreach (session('errors')->getBag('default')->all() as $message) {
            $this->assertRussianMessage($message, 'profile');
        }

        $html = $this->actingAs($tourist)->get(route('cabinet.profile'))->assertOk()->getContent();
        // Введённое значение не попадает в разметку неэкранированным.
        $this->assertStringNotContainsString($payload, $html);
    }

    public function test_manager_password_form_errors_are_russian(): void
    {
        $manager = $this->makeUser(Role::MANAGER);

        $this->actingAs($manager)
            ->from(route('cabinet.manager.settings'))
            ->post(route('cabinet.manager.settings.password'), [
                'current_password' => 'Wrong-Passw0rd-AO10',
                'password' => 'short1',
                'password_confirmation' => 'different1',
            ])
            ->assertSessionHasErrors(['current_password', 'password']);

        $errors = session('errors')->getBag('default');
        foreach ($errors->all() as $message) {
            $this->assertRussianMessage($message, 'manager password');
        }
        $this->assertStringContainsString('8', implode(' ', $errors->get('password')));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $manager->fresh()->password));
    }

    public function test_booking_form_errors_are_russian_with_form_labels(): void
    {
        $tourist = $this->makeUser(Role::TOURIST);

        $this->actingAs($tourist)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'departure_city' => '',
                'destination_country' => '',
                'start_date' => '2000-01-01',
                'nights' => 0,
                'adults' => 99,
                'children' => 0,
            ])
            ->assertSessionHasErrors(['departure_city', 'destination_country', 'start_date', 'nights', 'adults']);

        $errors = session('errors')->getBag('default');
        foreach ($errors->all() as $message) {
            $this->assertRussianMessage($message, 'booking');
        }
        $this->assertStringContainsString('Дата вылета (с)', $errors->first('start_date'));
        $this->assertStringNotContainsStringIgnoringCase('today', $errors->first('start_date'));
        $this->assertStringContainsString('10', $errors->first('adults'));
    }

    public function test_public_contact_form_errors_are_russian_including_captcha(): void
    {
        $this->from(route('contact.index'))
            ->post('/contact/send_contact', [
                'name' => '',
                'email' => 'bad',
                'message' => 'коротко',
                'captcha' => 'wrong',
                'agree' => '',
                'personal_data_consent' => '',
            ])
            ->assertSessionHasErrors(['name', 'email', 'message', 'captcha']);

        $messages = session('errors')->getBag('default')->all();
        $this->assertNotEmpty($messages);
        foreach ($messages as $message) {
            $this->assertRussianMessage($message, 'contact');
            $this->assertStringNotContainsString('validation.', $message);
        }
    }

    public function test_registration_errors_are_russian_and_keep_controller_specific_wording(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->from(route('register'))
            ->post(route('register'), [
                'name' => '',
                'email' => 'taken@example.test',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => 'Different-Passw0rd-AO10',
            ])
            ->assertSessionHasErrors(['name', 'email', 'password', 'user_agreement_accepted', 'personal_data_consent_accepted']);

        $errors = session('errors')->getBag('default');
        foreach ($errors->all() as $message) {
            $this->assertRussianMessage($message, 'register');
        }
        // Сообщения, заданные контроллером, имеют приоритет над каталогом.
        $this->assertSame(
            'Пожалуйста, примите условия Пользовательского соглашения',
            $errors->first('user_agreement_accepted')
        );
        $this->assertStringContainsString('Пароли не совпадают', $errors->first('password'));
        $this->assertStringContainsString('Электронная почта', $errors->first('email'));
    }

    public function test_message_receiver_participant_error_is_russian(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Message/MessageController.php'));

        $this->assertStringNotContainsString('The selected receiver', $source);
        $this->assertStringContainsString('Выбранный получатель не является участником этой заявки.', $source);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function assertRussianMessage(string $message, string $label): void
    {
        $this->assertNotSame('', $message, "{$label}: empty message");
        $this->assertMatchesRegularExpression('/\p{Cyrillic}/u', $message, "{$label}: not Russian: {$message}");
        $this->assertSame([], $this->strayLatin($message), "{$label}: English text in: {$message}");
        $this->assertStringNotContainsString('validation.', $message, "{$label}: raw translation key shown");
        $this->assertDoesNotMatchRegularExpression('/:(attribute|min|max|value|values|other|date|size|digits|format)\b/', $message, "{$label}: unreplaced placeholder in: {$message}");
    }

    /**
     * Слова латиницей, не входящие в список допустимых аббревиатур, подписей и
     * значений из правил. Значения вроде «pdf, doc» и подписи вроде «Email
     * клиента» не считаются ошибкой.
     *
     * @return list<string>
     */
    private function strayLatin(string $message): array
    {
        $clean = preg_replace('/:[a-z_]+/', '', $message);
        // Значения, перечисляемые правилами mimes/in и расширения файлов.
        $clean = preg_replace('/\b(?:pdf|docx?|jpe?g|png|xlsx?|gif|bmp|webp)\b/i', '', $clean);

        preg_match_all('/[A-Za-z][A-Za-z0-9]*/', $clean, $matches);

        return array_values(array_diff($matches[0], self::ALLOWED_LATIN));
    }

    /** @return list<string> */
    private function placeholders(string $message): array
    {
        preg_match_all('/:([a-z_]+)/', $message, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** @return array<string, mixed> */
    private function catalogue(string $locale): array
    {
        return require lang_path("{$locale}/validation.php");
    }

    /** @return array<string, mixed> */
    private function rules(string $locale): array
    {
        $catalogue = $this->catalogue($locale);
        unset($catalogue['custom'], $catalogue['attributes']);

        return $catalogue;
    }

    /** @return list<string> */
    private function flattenKeys(array $catalogue): array
    {
        $keys = array_keys($this->flatten($this->stripMeta($catalogue)));
        sort($keys);

        return $keys;
    }

    /** @return array<string, mixed> */
    private function stripMeta(array $catalogue): array
    {
        unset($catalogue['custom'], $catalogue['attributes']);

        return $catalogue;
    }

    /** @return array<string, string> */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $result += $this->flatten($value, $path);
            } else {
                $result[$path] = $value;
            }
        }

        return $result;
    }

    private function submitTouristPasswordForm(User $user, array $payload): string
    {
        return $this->actingAs($user)
            ->from(route('cabinet.settings'))
            ->followingRedirects()
            ->put(route('password.update'), $payload)
            ->assertOk()
            ->getContent();
    }

    /**
     * Текст `.invalid-feedback`, следующий за полем формы смены пароля, либо
     * null, если поле не помечено как невалидное.
     */
    private function fieldFeedback(string $html, string $field): ?string
    {
        $input = $this->xpath($html)->query('//form[@id="passwordForm"]//input[@id="' . $field . '"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $input, "Input #{$field} not found");

        if (! str_contains($input->getAttribute('class'), 'is-invalid')) {
            return null;
        }

        $feedback = $input->nextSibling;
        while ($feedback !== null && ! ($feedback instanceof DOMElement)) {
            $feedback = $feedback->nextSibling;
        }
        $this->assertInstanceOf(DOMElement::class, $feedback, "No feedback element after #{$field}");
        $this->assertStringContainsString('invalid-feedback', $feedback->getAttribute('class'));

        return trim($feedback->textContent);
    }

    private function assertPasswordUnchanged(User $user): void
    {
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $fresh->password));
        $this->assertFalse(Hash::check(self::NEW_PASSWORD, $fresh->password));
    }

    /** @param list<string> $extra */
    private function assertSecretsAbsent(string $html, array $extra = []): void
    {
        foreach (array_merge([self::OLD_PASSWORD, self::NEW_PASSWORD], $extra) as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private function makeUser(string $roleName): User
    {
        $user = User::factory()->create([
            'password' => Hash::make(self::OLD_PASSWORD),
            'password_change_required' => false,
        ]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
