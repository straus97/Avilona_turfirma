<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounts\ClientVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsAoaI2bFixtures;
use Tests\TestCase;

/**
 * AOA-01-I2B, G5 — менеджер видит только клиентов своей текущей работы; администратор — всех.
 * Только синтетические данные.
 */
class ClientVisibilityPrivacyTest extends TestCase
{
    use BuildsAoaI2bFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);
    }

    /** id клиентов, предложенных в выпадающем списке формы заявки. */
    private function dropdownIds(User $actor): array
    {
        $html = $this->actingAs($actor)->get(route('bookings.create'))->assertOk()->getContent();
        preg_match('/<select[^>]*name="client_id".*?<\/select>/s', $html, $select);
        preg_match_all('/<option value="(\d+)"/', $select[0] ?? '', $m);

        return array_map('intval', $m[1]);
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталья',
            'start_date' => now()->addMonths(2)->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
        ], $overrides);
    }

    public function test_admin_sees_the_complete_directory_on_clients_page_and_dropdown(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $mine = $this->userWith('tourist', ['name' => 'Клиент Мой']);
        $foreign = $this->userWith('tourist', ['name' => 'Клиент Чужой']);
        $this->bookingOf($mine, $manager);

        $this->actingAs($admin)->get(route('cabinet.manager.clients'))
            ->assertOk()->assertSee('Клиент Мой')->assertSee('Клиент Чужой');

        $ids = $this->dropdownIds($admin);
        $this->assertContains($mine->id, $ids);
        $this->assertContains($foreign->id, $ids);
    }

    public function test_manager_sees_clients_of_current_bookings_and_assigned_inquiries_only(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $viaBooking = $this->userWith('tourist', ['name' => 'Клиент Через Заявку']);
        $viaInquiry = $this->userWith('tourist', ['name' => 'Клиент Через Обращение']);
        $foreign = $this->userWith('tourist', ['name' => 'Клиент Другого Менеджера']);
        $stranger = $this->userWith('tourist', ['name' => 'Клиент Без Связей']);
        $this->bookingOf($viaBooking, $a);
        $this->bookingOf($foreign, $b);
        $this->inquiryFor($a, $viaInquiry);
        $this->inquiryFor($b, $stranger);

        $this->actingAs($a)->get(route('cabinet.manager.clients'))
            ->assertOk()
            ->assertSee('Клиент Через Заявку')->assertSee('Клиент Через Обращение')
            ->assertDontSee('Клиент Другого Менеджера')->assertDontSee('Клиент Без Связей');

        $this->assertEqualsCanonicalizing([$viaBooking->id, $viaInquiry->id], $this->dropdownIds($a));
    }

    public function test_scope_is_applied_in_sql_and_excludes_inactive_and_non_tourists(): void
    {
        $a = $this->userWith('manager');
        $inactive = $this->userWith('tourist', ['is_active' => false]);
        $this->bookingOf($inactive, $a);
        $ok = $this->userWith('tourist');
        $this->bookingOf($ok, $a);

        $this->assertSame([$ok->id], ClientVisibility::touristsVisibleTo($a)->pluck('users.id')->all());
        $this->assertSame(0, ClientVisibility::touristsVisibleTo($this->userWith('tourist'))->count());
    }

    public function test_foreign_client_cannot_be_found_by_name_phone_or_email_in_inquiry_search(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $foreign = $this->userWith('tourist', ['name' => 'Уникум Тестовый', 'email' => 'unikum.foreign@example.test', 'phone' => '+7 911 555-12-34']);
        $this->bookingOf($foreign, $b);
        $inquiry = $this->inquiryFor($a, null, ['client_email' => 'unikum.foreign@example.test', 'client_phone' => '+7 (911) 555-12-34']);

        // Совпадение контактов обращения с чужим клиентом (кандидаты) не раскрывается.
        $this->actingAs($a)->get(route('cabinet.manager.inquiries.show', $inquiry))
            ->assertOk()->assertDontSee('Выбрать клиента Уникум Тестовый')->assertDontSee('совпадает email')->assertDontSee('совпадает телефон');

        foreach (['Уникум', 'unikum.foreign', '555-12-34', '9115551234'] as $term) {
            $this->actingAs($a)->get(route('cabinet.manager.inquiries.show', [$inquiry, 'client_q' => $term]))
                ->assertOk()->assertDontSee('Выбрать клиента Уникум Тестовый');
        }

        // Администратор тот же клиент находит.
        $this->actingAs($admin)->get(route('cabinet.manager.inquiries.show', [$inquiry, 'client_q' => 'Уникум']))
            ->assertOk()->assertSee('Выбрать клиента Уникум Тестовый');
    }

    public function test_manager_can_search_and_select_a_client_they_legitimately_work_with(): void
    {
        $a = $this->userWith('manager');
        $mine = $this->userWith('tourist', ['name' => 'Постоянный Клиент']);
        $this->bookingOf($mine, $a);
        $inquiry = $this->inquiryFor($a);

        $this->actingAs($a)->get(route('cabinet.manager.inquiries.show', [$inquiry, 'client_q' => 'Постоянный']))
            ->assertOk()->assertSee('Выбрать клиента Постоянный Клиент');

        $this->actingAs($a)->post(route('cabinet.manager.inquiries.client.select', $inquiry), ['client_id' => $mine->id])
            ->assertSessionHas('success');
        $this->assertSame($mine->id, $inquiry->fresh()->client_user_id);
    }

    public function test_selecting_a_foreign_or_missing_client_gives_one_neutral_answer_without_details(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $foreign = $this->userWith('tourist', ['name' => 'Секретный Клиент', 'email' => 'secret.client@example.test']);
        $this->bookingOf($foreign, $b);
        $inquiry = $this->inquiryFor($a);

        $this->actingAs($a)->post(route('cabinet.manager.inquiries.client.select', $inquiry), ['client_id' => $foreign->id])
            ->assertSessionHasErrors('client_id');
        $foreignErrors = session('errors')->get('client_id');

        $this->actingAs($a)->post(route('cabinet.manager.inquiries.client.select', $inquiry), ['client_id' => 999999])
            ->assertSessionHasErrors('client_id');
        $missingErrors = session('errors')->get('client_id');

        $this->assertSame($missingErrors, $foreignErrors, 'Чужой и несуществующий клиент неразличимы');
        $message = implode(' ', $foreignErrors);
        $this->assertStringNotContainsString('Секретный', $message);
        $this->assertStringNotContainsString('secret.client', $message);
        $this->assertStringContainsString('администратор', $message);
        $this->assertNull($inquiry->fresh()->client_user_id);
    }

    public function test_booking_store_rejects_foreign_client_without_leaking_account_details(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $foreign = $this->userWith('tourist', ['name' => 'Секретный Клиент', 'email' => 'secret.client@example.test']);
        $this->bookingOf($foreign, $b);

        $this->actingAs($a)->post(route('bookings.store'), $this->bookingPayload(['client_id' => $foreign->id]))
            ->assertSessionHasErrors('client_id');

        $this->assertSame(1, Booking::count(), 'Новая заявка не создана');
        $text = implode(' ', session('errors')->all());
        $this->assertStringNotContainsString('Секретный', $text);
        $this->assertStringNotContainsString('secret.client', $text);
    }

    public function test_new_client_creation_still_works_for_manager(): void
    {
        $a = $this->userWith('manager');

        $this->actingAs($a)->post(route('bookings.store'), $this->bookingPayload([
            'is_new_client' => '1', 'client_name' => 'Совсем Новый', 'client_email' => 'brand.new@example.test',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $client = User::query()->where('email', 'brand.new@example.test')->sole();
        $this->assertSame($client->id, Booking::query()->sole()->user_id);
        $this->assertContains($client->id, ClientVisibility::touristsVisibleTo($a)->pluck('users.id')->all());

        $inquiry = $this->inquiryFor($a);
        $this->actingAs($a)->post(route('cabinet.manager.inquiries.client.create', $inquiry), ['client_name' => 'Из Обращения', 'client_email' => 'from.inquiry@example.test'])
            ->assertSessionHas('success');
        $this->assertNotNull($inquiry->fresh()->client_user_id);
    }

    public function test_existing_email_conflict_is_neutral_for_manager_and_informative_for_admin(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $existing = $this->userWith('tourist', ['name' => 'Уже Существующий', 'email' => 'existing.person@example.test']);
        $this->bookingOf($existing, $b);

        $this->actingAs($a)->post(route('bookings.store'), $this->bookingPayload([
            'is_new_client' => '1', 'client_name' => 'Дубликат', 'client_email' => 'existing.person@example.test',
        ]))->assertSessionHasErrors('client_email');
        $text = implode(' ', session('errors')->all());
        $this->assertStringNotContainsString('Уже Существующий', $text);
        $this->assertStringNotContainsString('выберите его из списка', $text);
        $this->assertStringContainsString('администратор', $text);

        $inquiry = $this->inquiryFor($a);
        $this->actingAs($a)->post(route('cabinet.manager.inquiries.client.create', $inquiry), ['client_name' => 'Дубликат', 'client_email' => 'existing.person@example.test'])
            ->assertSessionHasErrors('client_email');
        $this->assertStringNotContainsString('Уже Существующий', implode(' ', session('errors')->all()));
        $this->assertNull($inquiry->fresh()->client_user_id);
        $this->assertSame(1, User::query()->where('email', 'existing.person@example.test')->count(), 'Дубликат не создан');

        // Администратор видит весь справочник и получает прежнюю подсказку.
        $this->actingAs($admin)->post(route('bookings.store'), $this->bookingPayload([
            'is_new_client' => '1', 'client_name' => 'Дубликат', 'client_email' => 'existing.person@example.test',
        ]))->assertSessionHasErrors('client_email');
        $this->assertStringContainsString('выберите его из списка', implode(' ', session('errors')->all()));
    }

    public function test_admin_can_link_an_existing_foreign_client_to_a_managers_inquiry_without_exposing_other_records(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $shared = $this->userWith('tourist', ['name' => 'Общий Клиент']);
        $bBooking = $this->bookingOf($shared, $b, Booking::STATUS_PROGRESS, ['notes' => 'ПРИВАТНАЯ-ЗАМЕТКА-Б']);
        $inquiry = $this->inquiryFor($a);

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.client.select', $inquiry), ['client_id' => $shared->id])
            ->assertSessionHas('success');

        // Менеджер A теперь видит профиль клиента через своё обращение…
        $this->actingAs($a)->get(route('cabinet.manager.clients'))->assertOk()->assertSee('Общий Клиент');
        // …но не заявку менеджера B, её переписку и заметки.
        $this->actingAs($a)->get(route('bookings.show', $bBooking))->assertForbidden();
        $this->actingAs($a)->get(route('cabinet.manager.chat', ['bookingId' => $bBooking->id]))->assertDontSee('ПРИВАТНАЯ-ЗАМЕТКА-Б');
        // Дубликата аккаунта нет; менеджер B свой доступ сохраняет.
        $this->assertSame(1, User::query()->where('name', 'Общий Клиент')->count());
        $this->actingAs($b)->get(route('bookings.show', $bBooking))->assertOk();
    }

    public function test_shared_client_clients_page_counts_only_own_bookings(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $shared = $this->userWith('tourist', ['name' => 'Двойной Клиент']);
        $this->bookingOf($shared, $a, Booking::STATUS_CONFIRMED, ['destination_country' => 'Греция']);
        $this->bookingOf($shared, $b, Booking::STATUS_PROGRESS, ['destination_country' => 'Мальдивы']);
        $this->bookingOf($shared, $b, Booking::STATUS_PROGRESS, ['destination_country' => 'Мальдивы']);

        $response = $this->actingAs($a)->get(route('cabinet.manager.clients'))->assertOk();
        $this->assertSame(1, (int) $response->viewData('clients')->first()->bookings_count);
        $response->assertDontSee('Мальдивы');
    }

    public function test_reassignment_revokes_access_unless_another_relationship_remains(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $client = $this->userWith('tourist', ['name' => 'Уходящий Клиент']);
        $booking = $this->bookingOf($client, $a);

        $this->actingAs($a)->get(route('cabinet.manager.clients'))->assertSee('Уходящий Клиент');

        $this->actingAs($admin)->post(route('bookings.assign-manager', $booking), [
            'manager_id' => $b->id, 'expected_manager_id' => $a->id, 'reason_code' => 'workload',
        ])->assertSessionHasNoErrors();

        $this->actingAs($a)->get(route('cabinet.manager.clients'))->assertOk()->assertDontSee('Уходящий Клиент');
        $this->assertNotContains($client->id, $this->dropdownIds($a));
        $this->actingAs($b)->get(route('cabinet.manager.clients'))->assertSee('Уходящий Клиент');

        // Если у A остаётся другая связь (назначенное обращение с клиентом) — доступ сохраняется.
        $inquiry = $this->inquiryFor($a, $client);
        $this->actingAs($a)->get(route('cabinet.manager.clients'))->assertSee('Уходящий Клиент');

        // Переназначение обращения снимает и эту связь.
        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.reassign', $inquiry), [
            'assigned_to' => $b->id, 'expected_assignee_id' => $a->id, 'reason' => 'Нагрузка',
        ])->assertSessionHasNoErrors();
        $this->actingAs($a)->get(route('cabinet.manager.clients'))->assertDontSee('Уходящий Клиент');
    }

    public function test_tourists_and_guests_cannot_reach_staff_client_surfaces(): void
    {
        $tourist = $this->userWith('tourist');

        $this->get(route('cabinet.manager.clients'))->assertRedirect();
        $this->actingAs($tourist)->get(route('cabinet.manager.clients'))->assertForbidden();
        $this->assertSame([], $this->dropdownIds($tourist));
    }
}
