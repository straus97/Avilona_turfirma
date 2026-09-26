<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E5-A2A — служебный просмотр входящих обращений: только manager/admin,
 * явная пометка «не бронирование», источник Tourvisor, без действий-конверсий.
 */
class IncomingInquiryStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRoles(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['description' => Role::availableRoles()[$roleName] ?? $roleName]
            );
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function inquiry(array $overrides = []): IncomingInquiry
    {
        $inquiry = new IncomingInquiry();
        $inquiry->forceFill(array_merge([
            'provider' => 'tourvisor',
            'external_type' => 0,
            'external_id' => '1688615',
            'state' => IncomingInquiry::STATE_IMPORTED,
            'webhook_count' => 1,
            'first_notified_at' => now(),
            'last_notified_at' => now(),
            'imported_at' => now(),
            'client_name' => 'Тестовый Клиент',
            'client_phone' => '+70000000000',
            'client_email' => 'synthetic@example.test',
            'operator_name' => 'Sunmar',
            'destination_country' => 'Таиланд',
            'hotel_name' => 'TEST RESORT 3*',
            'price' => '70266',
            'currency' => 'RUB',
            'nights' => 11,
            'vendor_payload' => ['internal_marker' => 'RAW-PAYLOAD-MARKER'],
            'normalizer_version' => 1,
        ], $overrides))->save();

        return $inquiry;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $inquiry = $this->inquiry();

        $this->get(route('cabinet.manager.inquiries'))->assertRedirect(route('login'));
        $this->get(route('cabinet.manager.inquiries.show', $inquiry->id))->assertRedirect(route('login'));
    }

    public function test_tourist_cannot_see_inquiry_list_or_details(): void
    {
        $tourist = $this->userWithRoles(['tourist']);
        $inquiry = $this->inquiry();

        $this->actingAs($tourist)->get(route('cabinet.manager.inquiries'))->assertForbidden();
        $this->actingAs($tourist)->get(route('cabinet.manager.inquiries.show', $inquiry->id))->assertForbidden();
    }

    public function test_user_without_roles_cannot_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('cabinet.manager.inquiries'))->assertForbidden();
    }

    public function test_manager_sees_labelled_incoming_inquiry_with_provenance_and_state(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->inquiry();

        $list = $this->actingAs($manager)->get(route('cabinet.manager.inquiries'));
        $list->assertOk()
            ->assertSee('Входящие обращения')
            ->assertSee('Tourvisor #1688615')
            ->assertSee('Загружено')
            ->assertSee('Это не бронирования');

        $show = $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $inquiry->id));
        $show->assertOk()
            ->assertSee('Входящее обращение, не бронирование')
            ->assertSee('Источник: Tourvisor')
            ->assertSee('Тестовый Клиент')
            ->assertSee('Sunmar')
            ->assertSee('Таиланд')
            ->assertSee('Цена и наличие мест не подтверждены')
            ->assertDontSee('RAW-PAYLOAD-MARKER');
    }

    public function test_admin_has_access_too(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $inquiry = $this->inquiry();

        $this->actingAs($admin)->get(route('cabinet.manager.inquiries'))->assertOk()->assertSee('Tourvisor #1688615');
        $this->actingAs($admin)->get(route('cabinet.manager.inquiries.show', $inquiry->id))->assertOk();
    }

    public function test_failed_and_unsupported_states_are_shown_honestly(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $this->inquiry(['external_id' => '1', 'state' => IncomingInquiry::STATE_FAILED, 'last_error_code' => 'timeout', 'client_name' => null]);
        $this->inquiry(['external_id' => '2', 'external_type' => 1, 'state' => IncomingInquiry::STATE_UNSUPPORTED, 'client_name' => null]);

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries'))
            ->assertOk()
            ->assertSee('Ошибка загрузки')
            ->assertSee('Тип не обрабатывается')
            ->assertSee('(online)');
    }

    public function test_empty_state_and_missing_inquiry(): void
    {
        $manager = $this->userWithRoles(['manager']);

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries'))->assertOk()->assertSee('Входящих обращений пока нет');
        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', 999))->assertNotFound();
    }

    public function test_staff_pages_are_read_only_and_create_no_booking(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->inquiry();

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $inquiry->id));
        $this->actingAs($manager)->post('/cabinet/manager/inquiries')->assertStatus(405);

        $this->assertSame(0, Booking::count());
        $this->assertSame(IncomingInquiry::STATE_IMPORTED, $inquiry->fresh()->state);
    }
}
