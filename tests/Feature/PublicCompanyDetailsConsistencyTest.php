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
 * Stage 13: Public Company Details Consistency.
 *
 * Правило: страница /contacts обязана явно различать фактический
 * (клиентский) адрес офиса — Санкт-Петербург, ул. Генерала Симоняка, д. 10 —
 * и юридический адрес по ЕГРЮЛ — Санкт-Петербург, ул. Звенигородская, д. 22,
 * которые не совпадают и не должны подменять друг друга. Реквизиты компании
 * (ИНН/КПП/ОГРН) должны соответствовать актуальной выписке ЕГРЮЛ, включая
 * исправленный КПП 784001001 (устаревшее значение 780501001 не должно
 * встречаться на странице).
 *
 * Та же логика распространяется на главную страницу и на визитор-facing
 * подвалы автоответных писем (HomeFormMail/ContactFormMail — письма
 * менеджеру; UserFormMail — автоответ посетителю): нигде из них не должен
 * фигурировать несуществующий гибридный адрес (индекс 191119 + улица
 * Генерала Симоняка), а фактический адрес офиса должен быть явно подписан.
 */
class PublicCompanyDetailsConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const ACTUAL_OFFICE_ADDRESS = 'Генерала Симоняка, д. 10';

    private const ACTUAL_OFFICE_ADDRESS_FULL = '198261, Россия, Санкт-Петербург, ул. Генерала Симоняка, д. 10';

    private const INVALID_HYBRID_ADDRESS = '191119, Россия, Санкт-Петербург, ул. Генерала Симоняка';

    private const LEGAL_ADDRESS = 'Звенигородская, д. 22';

    private const INN = '7805502454';

    private const CURRENT_KPP = '784001001';

    private const STALE_KPP = '780501001';

    private const OGRN = '1097847289803';

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

    public function test_contacts_page_is_reachable(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertOk();
    }

    public function test_contacts_page_contains_actual_office_address(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertSee(self::ACTUAL_OFFICE_ADDRESS);
    }

    public function test_contacts_page_contains_legal_address(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertSee(self::LEGAL_ADDRESS);
    }

    public function test_contacts_page_labels_actual_and_legal_addresses_distinctly(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertSee('Фактический адрес');
        $response->assertSee('Юридический адрес');
    }

    public function test_contacts_page_contains_current_company_requisites(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertSee(self::INN);
        $response->assertSee(self::CURRENT_KPP);
        $response->assertSee(self::OGRN);
    }

    public function test_contacts_page_does_not_contain_stale_kpp(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertDontSee(self::STALE_KPP);
    }

    // -----------------------------------------------------------------------
    // Main layout (header/footer office address label)
    // -----------------------------------------------------------------------

    public function test_home_page_office_address_is_not_labelled_as_legal_address(): void
    {
        $response = $this->get(route('home.index'));

        $response->assertOk();
        $response->assertSee(self::ACTUAL_OFFICE_ADDRESS);
        $response->assertSee('Адрес офиса');
        $response->assertDontSee('Юридический адрес');
    }

    public function test_home_page_body_labels_office_address_as_actual_not_legal(): void
    {
        $response = $this->get(route('home.index'));

        // Домашняя страница дважды выводит подпись "Адрес офиса:" — один раз
        // в шапке/подвале layout'а, второй раз в собственном блоке контактов
        // на самой странице (resources/views/home.blade.php).
        $response->assertSeeInOrder(['Адрес офиса', self::ACTUAL_OFFICE_ADDRESS]);
    }

    // -----------------------------------------------------------------------
    // Auto-reply email footers (HomeFormMail / ContactFormMail / UserFormMail)
    // -----------------------------------------------------------------------

    public function test_home_form_mail_to_manager_uses_actual_office_address_without_hybrid(): void
    {
        Mail::fake();

        $this->post(route('contact.send_home'), $this->validPayload());

        Mail::assertSent(HomeFormMail::class, function (HomeFormMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, 'Адрес офиса')
                && str_contains($html, self::ACTUAL_OFFICE_ADDRESS_FULL)
                && ! str_contains($html, self::INVALID_HYBRID_ADDRESS);
        });
    }

    public function test_contact_form_mail_to_manager_uses_actual_office_address_without_hybrid(): void
    {
        Mail::fake();

        $this->post(route('contact.send_contact'), $this->validPayload());

        Mail::assertSent(ContactFormMail::class, function (ContactFormMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, 'Адрес офиса')
                && str_contains($html, self::ACTUAL_OFFICE_ADDRESS_FULL)
                && ! str_contains($html, self::INVALID_HYBRID_ADDRESS);
        });
    }

    public function test_user_form_mail_auto_reply_uses_actual_office_address_not_legal_address(): void
    {
        Mail::fake();

        $payload = $this->validPayload();

        $this->post(route('contact.send_home'), $payload);

        Mail::assertSent(UserFormMail::class, function (UserFormMail $mail) use ($payload): bool {
            if (! $mail->hasTo($payload['email'])) {
                return false;
            }

            $html = $mail->render();

            // user-form-contacts.blade.php — это визитор-facing автоответ
            // (подпись "С любовью, турфирма Авилона!", без реквизитов/ИНН/
            // ОГРН), поэтому здесь корректен фактический адрес офиса, а не
            // юридический адрес по ЕГРЮЛ.
            return str_contains($html, 'Адрес офиса')
                && str_contains($html, self::ACTUAL_OFFICE_ADDRESS_FULL)
                && ! str_contains($html, self::INVALID_HYBRID_ADDRESS)
                && ! str_contains($html, self::LEGAL_ADDRESS);
        });
    }
}
