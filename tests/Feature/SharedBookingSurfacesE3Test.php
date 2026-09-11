<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E3-A3 — редизайн shared-поверхностей заявки (bookings/show, /edit, /create).
 *
 * Тесты проверяют презентационные контракты среза, не дублируя глубокие
 * security-тесты BookingPolicy / BookingLifecycleAuthorization / документов:
 *  - каноническая формулировка статуса едина для всех хранимых значений;
 *  - роль-адресные действия и ссылки чата на странице показа;
 *  - сохранение form action/method форм редактирования и создания;
 *  - безопасный рендер пустых/необязательных полей;
 *  - отсутствие ложных заявлений о поиске туров в реальном времени.
 */
class SharedBookingSurfacesE3Test extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $roleNames, bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active]);

        foreach ($roleNames as $name) {
            $role = Role::query()->firstOrCreate(
                ['name' => $name],
                ['description' => Role::availableRoles()[$name] ?? $name]
            );
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function makeBooking(User $owner, ?int $managerId = null, string $status = Booking::STATUS_NEW, array $overrides = []): Booking
    {
        return Booking::withoutEvents(fn (): Booking => Booking::query()->create(array_merge([
            'user_id'             => $owner->id,
            'manager_id'          => $managerId,
            'status'              => $status,
            'departure_city'      => 'Санкт-Петербург',
            'destination_country' => 'Турция',
            'destination_city'    => 'Анталья',
            'start_date'          => '2026-08-15',
            'nights'              => 7,
            'adults'              => 2,
            'children'            => 0,
        ], $overrides)));
    }

    // ----------------------------------------------------------------- SHOW

    public function test_owner_tourist_can_render_own_booking(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner);

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Заявка №' . $booking->id, false);
    }

    public function test_foreign_tourist_cannot_render_another_tourists_booking(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $other   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner);

        $this->actingAs($other)
            ->get(route('bookings.show', $booking))
            ->assertForbidden();
    }

    public function test_show_uses_canonical_progress_label_not_legacy_wording(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $response = $this->actingAs($owner)->get(route('bookings.show', $booking))->assertOk();

        $response->assertSee('В обработке', false);
        $response->assertDontSee('В работе', false);
        // Каноничность закреплена доменным контрактом модели.
        $this->assertSame('В обработке', $booking->fresh()->status_label);
        $this->assertSame('В обработке', Booking::availableStatuses()[Booking::STATUS_PROGRESS]);
    }

    public function test_show_renders_safely_without_optional_price_and_notes(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW, [
            'total_price'      => null,
            'notes'            => null,
            'destination_city' => null,
            'start_date_end'   => null,
            'nights_max'       => null,
        ]);

        $response = $this->actingAs($owner)->get(route('bookings.show', $booking))->assertOk();

        $response->assertDontSee('>null<', false);
        $response->assertDontSee('Стоимость и оплата', false);
    }

    public function test_show_exposes_role_aware_chat_links(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $admin   = $this->makeUser([Role::ADMIN]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($owner)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('href="' . route('cabinet.chat', $booking->id) . '"', false);

        $this->actingAs($manager)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('href="' . route('cabinet.manager.chat', ['bookingId' => $booking->id]) . '"', false);

        $this->actingAs($admin)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('href="' . route('cabinet.admin.chats', ['bookingId' => $booking->id]) . '"', false);
    }

    public function test_show_hides_tourist_chat_link_until_manager_assigned(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner);

        $this->actingAs($owner)->get(route('bookings.show', $booking))->assertOk()
            ->assertDontSee('href="' . route('cabinet.chat', $booking->id) . '"', false)
            ->assertSee('Ответственный ещё не назначен', false);
    }

    // ----------------------------------------------------------------- EDIT

    public function test_edit_preserves_form_action_and_method_for_staff(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $response = $this->actingAs($manager)->get(route('bookings.edit', $booking))->assertOk();

        $response->assertSee('action="' . route('bookings.update', $booking) . '"', false);
        $response->assertSee('name="_method" value="PUT"', false);
        $response->assertSee('name="status"', false);
        $response->assertSee('name="manager_notes"', false);
        $response->assertSee('name="total_price"', false);
    }

    public function test_edit_route_denied_for_non_owning_tourist_policy_unchanged(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner);

        // Турист вообще не может открыть форму редактирования (policy update = false).
        $this->actingAs($owner)
            ->get(route('bookings.edit', $booking))
            ->assertForbidden();
    }

    public function test_edit_status_select_only_offers_allowed_transitions(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $admin   = $this->makeUser([Role::ADMIN]);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $response = $this->actingAs($admin)->get(route('bookings.edit', $booking))->assertOk();

        $response->assertSee('value="new"', false);
        $response->assertSee('value="cancelled"', false);
        $response->assertDontSee('value="completed"', false);
    }

    // --------------------------------------------------------------- CREATE

    public function test_tourist_sees_application_form_with_preserved_contract(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $response = $this->actingAs($tourist)->get(route('bookings.create'))->assertOk();

        $response->assertSee('action="' . route('bookings.store') . '"', false);
        $response->assertSee('method="POST"', false);
        $response->assertSee('name="departure_city"', false);
        $response->assertSee('name="destination_country"', false);
        $response->assertSee('name="start_date"', false);
        $response->assertSee('name="nights"', false);
        $response->assertSee('name="adults"', false);
        // Форма клиента — только для персонала.
        $response->assertDontSee('name="client_id"', false);
    }

    public function test_create_does_not_claim_realtime_tour_search_or_inventory(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $response = $this->actingAs($tourist)->get(route('bookings.create'))->assertOk();

        $response->assertSee('Это заявка-обращение, а не бронирование в реальном времени', false);
        $response->assertDontSee('мест осталось', false);
        $response->assertDontSee('Найти тур', false);
        $response->assertDontSee('Забронировать сейчас', false);
    }

    public function test_create_keeps_old_input_after_validation_error(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $response = $this->actingAs($tourist)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'departure_city'      => 'Казань',
                'destination_country' => '',
                'start_date'          => '',
                'nights'              => 5,
                'adults'              => 3,
            ]);

        $response->assertRedirect(route('bookings.create'));

        $this->actingAs($tourist)->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('value="Казань"', false);
    }

    // ------------------------------------------------ CREATE / DEPARTURE CITY

    public function test_create_defaults_departure_city_to_saint_petersburg(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $this->actingAs($tourist)->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('id="departure_city"', false)
            ->assertSee('value="Санкт-Петербург"', false);
    }

    public function test_departure_city_datalist_has_fallback_cities_with_zero_tours(): void
    {
        $this->assertSame(0, Tour::query()->count());

        $tourist  = $this->makeUser([Role::TOURIST]);
        $response = $this->actingAs($tourist)->get(route('bookings.create'))->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('id="departureCitiesList"', $html);

        foreach (['Санкт-Петербург', 'Москва', 'Екатеринбург', 'Новосибирск', 'Казань'] as $city) {
            $response->assertSee('<option value="' . $city . '">', false);
        }

        // Санкт-Петербург встречается ровно один раз внутри даталиста подсказок.
        $datalist = substr($html, (int) strpos($html, 'id="departureCitiesList"'));
        $datalist = substr($datalist, 0, (int) strpos($datalist, '</datalist>'));
        $this->assertSame(1, substr_count($datalist, '<option value="Санкт-Петербург">'));
    }

    public function test_departure_city_datalist_merges_additional_catalog_city(): void
    {
        Tour::factory()->create(['departure_city' => 'Самара']);

        $tourist = $this->makeUser([Role::TOURIST]);

        $this->actingAs($tourist)->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('<option value="Самара">', false);
    }

    public function test_departure_city_datalist_does_not_duplicate_fallback_city_from_catalog(): void
    {
        Tour::factory()->create(['departure_city' => 'Москва']);

        $tourist  = $this->makeUser([Role::TOURIST]);
        $response = $this->actingAs($tourist)->get(route('bookings.create'))->assertOk();

        $html     = $response->getContent();
        $datalist = substr($html, (int) strpos($html, 'id="departureCitiesList"'));
        $datalist = substr($datalist, 0, (int) strpos($datalist, '</datalist>'));

        $this->assertSame(1, substr_count($datalist, '<option value="Москва">'));
    }

    public function test_departure_city_datalist_ignores_blank_catalog_values(): void
    {
        Tour::factory()->create(['departure_city' => '']);
        Tour::factory()->create(['departure_city' => '   ']);

        $tourist  = $this->makeUser([Role::TOURIST]);
        $response = $this->actingAs($tourist)->get(route('bookings.create'))->assertOk();

        $html     = $response->getContent();
        $datalist = substr($html, (int) strpos($html, 'id="departureCitiesList"'));
        $datalist = substr($datalist, 0, (int) strpos($datalist, '</datalist>'));

        $this->assertStringNotContainsString('<option value="">', $datalist);
    }

    public function test_store_accepts_arbitrary_manual_departure_city_not_in_datalist(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $response = $this->actingAs($tourist)->post(route('bookings.store'), [
            'departure_city'      => 'Владивосток',
            'destination_country' => 'Турция',
            'start_date'          => now()->addDays(10)->format('Y-m-d'),
            'nights'              => 7,
            'adults'              => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'user_id'        => $tourist->id,
            'departure_city' => 'Владивосток',
        ]);
    }

    // ------------------------------------------------ CREATE / VALIDATION UX

    public function test_create_form_disables_native_html5_validation(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $response = $this->actingAs($tourist)->get(route('bookings.create'))->assertOk();

        $response->assertSee('id="bookingForm" novalidate', false);
    }

    public function test_invalid_store_redirects_back_with_error_summary(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $this->actingAs($tourist)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'departure_city'      => 'Москва',
                'destination_country' => '',
                'start_date'          => '',
                'nights'              => 7,
                'adults'              => 2,
            ])
            ->assertRedirect(route('bookings.create'))
            ->assertSessionHasErrors(['destination_country', 'start_date']);

        $response = $this->actingAs($tourist)->get(route('bookings.create'))->assertOk();

        $response->assertSee('Проверьте правильность заполнения формы', false);
        $response->assertSee('role="alert"', false);
        // Поле остаётся заполненным старым значением.
        $response->assertSee('value="Москва"', false);
        // Полевая ошибка продолжает рендериться рядом с самим полем.
        $response->assertSee('invalid-feedback', false);
    }

    public function test_invalid_store_renders_only_one_validation_summary(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $this->actingAs($tourist)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'departure_city'      => 'Москва',
                'destination_country' => '',
                'start_date'          => '',
                'nights'              => 7,
                'adults'              => 2,
            ])
            ->assertRedirect(route('bookings.create'));

        $html = $this->actingAs($tourist)->get(route('bookings.create'))->assertOk()->getContent();

        // Ровно один заголовок сводки ошибок валидации — дублирующей
        // локальной сводки на странице создания больше нет.
        $this->assertSame(1, substr_count($html, 'Проверьте правильность заполнения формы'));
        $this->assertStringNotContainsString('Проверьте обязательные поля формы', $html);
    }

    public function test_invalid_store_uses_human_readable_russian_field_labels(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $this->actingAs($tourist)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'departure_city'      => 'Москва',
                'destination_country' => '',
                'start_date'          => '',
                'nights'              => 7,
                'adults'              => 2,
            ])
            ->assertRedirect(route('bookings.create'));

        $html = $this->actingAs($tourist)->get(route('bookings.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Дата вылета (с)', $html);
        $this->assertStringContainsString('Страна', $html);
        $this->assertStringNotContainsString('start date', $html);
        $this->assertStringNotContainsString('destination country', $html);
    }

    // ----------------------------------------------------- SHOW / NEXT STEP

    public function test_assigned_manager_new_booking_is_not_told_to_assign_anyone(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_NEW);

        $response = $this->actingAs($manager)->get(route('bookings.show', $booking))->assertOk();

        $response->assertDontSee('Назначьте ответственного', false);
        $response->assertSee('Заявка назначена вам и ожидает начала обработки.', false);
    }

    public function test_admin_new_booking_without_assignee_gets_assignment_copy(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $admin   = $this->makeUser([Role::ADMIN]);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $this->actingAs($admin)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('Назначьте ответственного сотрудника для начала обработки.', false);
    }

    public function test_admin_new_booking_with_other_assignee_does_not_demand_assignment(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $admin   = $this->makeUser([Role::ADMIN]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_NEW);

        $response = $this->actingAs($admin)->get(route('bookings.show', $booking))->assertOk();

        $response->assertDontSee('Назначьте ответственного', false);
        $response->assertSee('Ответственный сотрудник уже назначен.', false);
    }

    public function test_admin_new_booking_assigned_to_self_gets_assigned_to_you_copy(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $admin   = $this->makeUser([Role::ADMIN]);
        $booking = $this->makeBooking($owner, $admin->id, Booking::STATUS_NEW);

        $response = $this->actingAs($admin)->get(route('bookings.show', $booking))->assertOk();

        $response->assertDontSee('Назначьте ответственного', false);
        $response->assertSee('Заявка назначена вам и ожидает начала обработки.', false);
    }

    public function test_terminal_status_copy_is_staff_neutral_for_manager_and_admin(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $admin   = $this->makeUser([Role::ADMIN]);

        $completed = $this->makeBooking($owner, $manager->id, Booking::STATUS_COMPLETED);
        $this->actingAs($manager)->get(route('bookings.show', $completed))->assertOk()
            ->assertDontSee('Спасибо, что путешествуете', false);
        $this->actingAs($admin)->get(route('bookings.show', $completed))->assertOk()
            ->assertDontSee('Спасибо, что путешествуете', false);

        $cancelled = $this->makeBooking($owner, $manager->id, Booking::STATUS_CANCELLED);
        $this->actingAs($manager)->get(route('bookings.show', $cancelled))->assertOk()
            ->assertDontSee('действия по ней больше недоступны', false);
        $this->actingAs($admin)->get(route('bookings.show', $cancelled))->assertOk()
            ->assertDontSee('действия по ней больше недоступны', false);
    }

    public function test_progress_current_state_copy_uses_canonical_terminology(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        foreach ([$owner, $manager] as $viewer) {
            $response = $this->actingAs($viewer)->get(route('bookings.show', $booking))->assertOk();
            $response->assertSee('В обработке', false);
            $response->assertDontSee('Заявка в работе', false);
        }
    }

    public function test_owner_memo_renders_for_tourist_and_reflects_assignment_state(): void
    {
        $owner = $this->makeUser([Role::TOURIST]);

        $unassigned = $this->makeBooking($owner, null, Booking::STATUS_NEW);
        $this->actingAs($owner)->get(route('bookings.show', $unassigned))->assertOk()
            ->assertSee('Чат станет доступен после назначения.', false);

        $manager  = $this->makeUser([Role::MANAGER]);
        $assigned = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);
        $this->actingAs($owner)->get(route('bookings.show', $assigned))->assertOk()
            ->assertSee('используйте чат с ответственным сотрудником', false);
    }

    public function test_customer_memo_is_absent_for_manager_and_admin(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $admin   = $this->makeUser([Role::ADMIN]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($manager)->get(route('bookings.show', $booking))->assertOk()
            ->assertDontSee('своему менеджеру', false)
            ->assertDontSee('используйте чат с ответственным сотрудником', false);
        $this->actingAs($admin)->get(route('bookings.show', $booking))->assertOk()
            ->assertDontSee('своему менеджеру', false);
    }

    public function test_payment_refund_copy_matches_business_facts(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW, ['total_price' => 120000]);

        $this->actingAs($owner)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('Возврат средств производится на банковскую карту', false)
            ->assertSee('зависит от условий и решения туроператора', false);
    }

    // -------------------------------------------------------- CREATE / COPY

    public function test_tourist_create_has_no_24h_sla_and_shows_no_sla_guidance(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $response = $this->actingAs($tourist)->get(route('bookings.create'))->assertOk();

        $response->assertDontSee('24 часов', false);
        $response->assertSee('сотрудник свяжется с вами для уточнения деталей', false);
    }

    public function test_staff_create_copy_does_not_address_creator_as_customer(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('bookings.create'))->assertOk();

        $response->assertDontSee('менеджер свяжется с вами', false);
        $response->assertDontSee('24 часов', false);
        $response->assertSee('После создания заявка будет доступна для дальнейшей работы.', false);
    }

    // --------------------------------------------------------------- STATUS

    public function test_every_stored_status_maps_to_single_presentation_source(): void
    {
        $component = base_path('resources/views/cabinet/components/status-badge.blade.php');
        $rendered = [];

        foreach (array_keys(Booking::availableStatuses()) as $status) {
            $html = view('cabinet.components.status-badge', ['status' => $status])->render();
            $rendered[$status] = trim(strip_tags($html));
        }

        $this->assertSame(Booking::availableStatuses(), $rendered);
        $this->assertFileExists($component);
    }
}
