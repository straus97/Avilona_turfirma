<?php

namespace Tests\Feature;

use App\Models\ClientVerificationRequest;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-04-I4, Gate D — карточка обращения: разделы «Контакт клиента», «Клиент», «Данные поездки»,
 * различия ролей, доступная разметка и список. Синтетические данные, SQLite :memory:.
 */
class InquiryCardClientUxTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private User $admin;
    private User $a;
    private User $b;
    private User $own;
    private User $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        $this->admin = $this->userWith('admin', ['name' => 'Админ Синтетический']);
        $this->a = $this->userWith('manager', ['name' => 'Анна Менеджер']);
        $this->b = $this->userWith('manager', ['name' => 'Борис Менеджер']);
        $this->own = $this->userWith('tourist', ['name' => 'Повторный Клиент', 'email' => 'repeat.client@example.test']);
        $this->foreign = $this->userWith('tourist', ['name' => 'Чужой Клиент', 'email' => 'foreign.client@example.test']);
        $this->bookingOf($this->own, $this->a);
        $this->bookingOf($this->foreign, $this->b);
    }

    private function inquiry(?User $manager = null): Inquiry
    {
        return Inquiry::factory()->managerCreated($manager ?? $this->a)->create()->refresh();
    }

    private function card(User $actor, Inquiry $inquiry): string
    {
        return $this->actingAs($actor)->get(route('cabinet.manager.agency-inquiries.show', $inquiry))->assertOk()->getContent();
    }

    private function saveContact(User $actor, Inquiry $inquiry, int $revision = 0): void
    {
        $this->actingAs($actor)->put(route('cabinet.manager.agency-inquiries.contact.update', $inquiry), [
            'revision' => $revision, 'contact_name' => 'Ирина Контактова', 'contact_phone' => '+7 900 123-45-67', 'contact_messenger' => 'Telegram @irina',
        ])->assertSessionHasNoErrors();
    }

    public function test_sections_appear_in_working_order_with_labelled_accessible_forms(): void
    {
        $inquiry = $this->inquiry();
        $html = $this->card($this->a, $inquiry);

        $contact = strpos($html, 'id="agency-inquiry-contact-title"');
        $client = strpos($html, 'id="agency-inquiry-client-title"');
        $draft = strpos($html, 'id="agency-inquiry-draft-title"');
        $this->assertNotFalse($contact);
        $this->assertTrue($contact < $client && $client < $draft, 'контакт → клиент → данные поездки');

        foreach ([
            'ai-contact-name', 'ai-contact-phone', 'ai-contact-messenger', 'ai-client-q', 'ai-req-name', 'ai-req-email', 'ai-req-phone',
        ] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html);
            $this->assertStringContainsString('for="' . $id . '"', $html, "у поля {$id} есть подпись");
        }
        $this->assertStringContainsString('aria-labelledby="agency-inquiry-contact-title"', $html);
        $this->assertStringContainsString('aria-labelledby="agency-inquiry-client-title"', $html);
        $this->assertStringContainsString('data-submit-once', $html);
        $this->assertStringContainsString('Контакт не указан.', $html);
        $this->assertStringContainsString('Аккаунт клиента не привязан', $html);
        $this->assertStringContainsString('Данные поездки', $html);
    }

    public function test_manager_never_sees_admin_only_controls_or_account_existence_hints(): void
    {
        $inquiry = $this->inquiry();
        $this->saveContact($this->a, $inquiry);

        $html = $this->card($this->a, $inquiry);
        foreach (['Нового клиента создавать', 'Снять привязку', 'Создать клиента и привязать', 'Рассмотреть запрос', 'agency-inquiry-pending-requests', 'карточка клиента', 'Поиск по справочнику'] as $adminOnly) {
            $this->assertStringNotContainsString($adminOnly, $html, "менеджеру не показывается: {$adminOnly}");
        }
        $this->assertStringNotContainsString(route('cabinet.manager.agency-inquiries.client.create', $inquiry), $html);
        $this->assertStringNotContainsString(route('cabinet.manager.agency-inquiries.client.unlink', $inquiry), $html);
        $this->assertStringNotContainsString('foreign.client@example.test', $html);
        $this->assertStringNotContainsString('Чужой Клиент', $html);

        // Форма запроса предзаполнена из контакта обращения, email — пустой.
        $this->assertStringContainsString('value="Ирина Контактова"', $html);
        $this->assertStringContainsString('Поиск только среди клиентов, с которыми вы работаете сейчас.', $html);
        $this->assertStringContainsString('Отправить запрос на проверку клиента', $html);
    }

    public function test_admin_sees_search_create_and_pending_requests_but_not_the_manager_request_form(): void
    {
        $inquiry = $this->inquiry();
        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.client.verification', $inquiry), ['client_name' => 'Клиент Запроса'])->assertSessionHasNoErrors();
        $request = ClientVerificationRequest::query()->sole();

        $html = $this->card($this->admin, $inquiry);
        $this->assertStringContainsString('Поиск по справочнику', $html);
        $this->assertStringContainsString('Создать клиента и привязать', $html);
        $this->assertStringContainsString('Запросы менеджера на проверку клиента', $html);
        $this->assertStringContainsString(route('cabinet.admin.client-requests.show', $request), $html);
        $this->assertStringContainsString('Анна Менеджер', $html);
        $this->assertStringNotContainsString('Отправить запрос на проверку клиента', $html);
        $this->assertStringNotContainsString(route('cabinet.manager.agency-inquiries.client.verification', $inquiry), $html);
    }

    public function test_request_state_machine_for_the_manager(): void
    {
        $inquiry = $this->inquiry();
        $url = route('cabinet.manager.agency-inquiries.client.verification', $inquiry);

        $this->actingAs($this->a)->post($url, ['client_name' => 'Клиент', 'client_email' => 'x@example.test'])->assertSessionHasNoErrors();
        $html = $this->card($this->a, $inquiry);
        $this->assertStringContainsString('На проверке у администратора', $html);
        $this->assertStringContainsString('Повторно отправлять запрос не нужно.', $html);
        $this->assertStringNotContainsString('id="ai-req-name"', $html, 'пока запрос открыт, форма не показывается');

        $request = ClientVerificationRequest::query()->sole();
        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.reject', $request), ['note' => 'Клиент не подтверждён'])->assertSessionHas('success');
        $html = $this->card($this->a, $inquiry);
        $this->assertStringContainsString('Отклонён', $html);
        $this->assertStringContainsString('Комментарий администратора: Клиент не подтверждён', $html);
        $this->assertStringContainsString('id="ai-req-name"', $html, 'после отклонения можно подать новый запрос');

        // Подтверждённый запрос превращается в привязанного клиента; способ решения менеджеру не раскрывается.
        $this->actingAs($this->a)->post($url, ['client_name' => 'Клиент', 'client_email' => 'x@example.test'])->assertSessionHasNoErrors();
        $second = ClientVerificationRequest::query()->orderByDesc('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.link', $second), ['user_id' => $this->own->id])->assertSessionHas('success');
        $html = $this->card($this->a, $inquiry);
        $this->assertStringContainsString('Привязан клиент:', $html);
        $this->assertStringContainsString('Повторный Клиент', $html);
        $this->assertStringNotContainsString('Привязан существующий аккаунт', $html);
        $this->assertStringNotContainsString('Создан новый аккаунт', $html);
        $this->assertStringNotContainsString('id="ai-req-name"', $html);
        $this->assertStringNotContainsString('Снять привязку', $html);
    }

    public function test_admin_sees_the_linked_client_and_the_unlink_form(): void
    {
        $inquiry = $this->inquiry();
        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.client.select', $inquiry), ['user_id' => $this->foreign->id, 'expected_client_id' => null]);

        $html = $this->card($this->admin, $inquiry);
        $this->assertStringContainsString('Привязан клиент:', $html);
        $this->assertStringContainsString('Чужой Клиент', $html);
        $this->assertStringContainsString('Привязал: Админ Синтетический', $html);
        $this->assertStringContainsString('Снять привязку клиента', $html);
        $this->assertStringContainsString('name="expected_client_id" value="' . $this->foreign->id . '"', $html);
        $this->assertStringNotContainsString('Нового клиента создавать', $html, 'при привязанном клиенте создание не предлагается');
    }

    public function test_validation_keeps_ordinary_input_and_shows_field_errors(): void
    {
        $inquiry = $this->inquiry();
        $show = route('cabinet.manager.agency-inquiries.show', $inquiry);

        $this->actingAs($this->a)->from($show)->post(route('cabinet.manager.agency-inquiries.client.verification', $inquiry), [
            'client_name' => 'Сохранить Меня', 'client_email' => 'не-почта', 'client_phone' => '+7 900 000-00-00',
        ])->assertRedirect($show)->assertSessionHasErrors('client_email');

        $html = $this->actingAs($this->a)->withSession(['_old_input' => ['client_name' => 'Сохранить Меня', 'client_phone' => '+7 900 000-00-00'], 'errors' => new \Illuminate\Support\ViewErrorBag()])->get($show)->assertOk()->getContent();
        $this->assertStringContainsString('value="Сохранить Меня"', $html);
        $this->assertStringContainsString('value="+7 900 000-00-00"', $html);
    }

    public function test_stale_contact_form_shows_the_conflict_for_comparison(): void
    {
        $inquiry = $this->inquiry();
        $this->saveContact($this->a, $inquiry);

        $response = $this->actingAs($this->a)->put(route('cabinet.manager.agency-inquiries.contact.update', $inquiry), [
            'revision' => 0, 'contact_name' => 'Устаревшая правка', 'contact_phone' => '', 'contact_messenger' => '',
        ])->assertRedirect(route('cabinet.manager.agency-inquiries.show', $inquiry))->assertSessionHas('error');

        $html = $this->followRedirects($response)->assertOk()->getContent();
        $this->assertStringContainsString('Ваши несохранённые значения контакта', $html);
        $this->assertStringContainsString('Устаревшая правка', $html);
        $this->assertStringContainsString('Ирина Контактова', $html, 'актуальный контакт показан в форме');
    }

    public function test_closed_inquiry_is_read_only(): void
    {
        $inquiry = $this->inquiry();
        $this->saveContact($this->a, $inquiry);
        DB::table('inquiries')->where('id', $inquiry->id)->update(['status' => Inquiry::STATUS_CLOSED, 'closed_at' => now()]);

        $html = $this->card($this->a, $inquiry);
        $this->assertStringContainsString('Ирина Контактова', $html);
        $this->assertStringNotContainsString('Сохранить контакт', $html);
        $this->assertStringNotContainsString('Очистить контакт', $html);
        $this->assertStringNotContainsString('Отправить запрос на проверку клиента', $html);
        $this->assertStringNotContainsString('Найти', $html);

        $adminHtml = $this->card($this->admin, $inquiry);
        $this->assertStringNotContainsString('Создать клиента и привязать', $adminHtml);
    }

    public function test_contact_survives_reassignment_while_the_former_manager_is_locked_out(): void
    {
        $inquiry = $this->inquiry();
        $this->saveContact($this->a, $inquiry);

        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.assign', $inquiry), [
            'assignee_id' => $this->b->id, 'expected_assignee_id' => $this->a->id, 'reason' => 'Синтетическая передача',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries.show', $inquiry))->assertNotFound();
        $this->assertStringContainsString('Ирина Контактова', $this->card($this->b, $inquiry));
        $this->assertStringNotContainsString('Ирина Контактова', $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries'))->getContent());
    }

    public function test_list_shows_the_contact_name_and_linked_badge_only_for_visible_inquiries(): void
    {
        $mine = $this->inquiry();
        $this->saveContact($this->a, $mine);
        $theirs = $this->inquiry($this->b);
        $this->actingAs($this->b)->put(route('cabinet.manager.agency-inquiries.contact.update', $theirs), [
            'revision' => 0, 'contact_name' => 'Чужой Контакт', 'contact_phone' => '', 'contact_messenger' => '',
        ]);
        $empty = $this->inquiry();
        $linked = $this->independentInquiry($this->a, null, $this->own);

        $html = $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries'))->assertOk()->getContent();
        $this->assertStringContainsString('<th>Клиент</th>', $html);
        $this->assertStringContainsString('Ирина Контактова', $html);
        $this->assertStringContainsString('Контакт не указан', $html);
        $this->assertStringContainsString('Аккаунт привязан', $html);
        $this->assertStringNotContainsString('Чужой Контакт', $html);
        $this->assertStringNotContainsString('+7 900 123-45-67', $html, 'телефон в списке не показывается');
        $this->assertStringNotContainsString('Повторный Клиент', $html, 'имя аккаунта в списке не показывается');

        $adminHtml = $this->actingAs($this->admin)->get(route('cabinet.manager.agency-inquiries'))->assertOk()->getContent();
        $this->assertStringContainsString('Чужой Контакт', $adminHtml);
        $this->assertNotNull($empty->id);
        $this->assertNotNull($linked->id);
    }

    public function test_existing_card_behaviour_is_preserved(): void
    {
        $inquiry = $this->inquiry();

        $this->actingAs($this->a)->put(route('cabinet.manager.agency-inquiries.draft.update', $inquiry), ['revision' => 0, 'destination_country' => 'Турция'])
            ->assertSessionHas('success');
        $html = $this->card($this->a, $inquiry);
        $this->assertStringContainsString('value="Турция"', $html);
        $this->assertStringNotContainsString('Ответственный менеджер', $html, 'блок назначения — только администратору');
        $this->assertStringContainsString('Ответственный менеджер', $this->card($this->admin, $inquiry));
        $this->assertStringNotContainsString('будут добавлены отдельным шагом', $html);
    }
}
