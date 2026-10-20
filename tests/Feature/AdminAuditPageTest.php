<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AOA-01-I1 — Admin-only business audit page.
 */
class AdminAuditPageTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function record(array $metadata = [], ?User $actor = null, AuditEventType $type = AuditEventType::AdminAction): AuditEvent
    {
        return $this->app->make(AuditRecorder::class)->record($type, $actor, 'incoming_inquiry', 11, $metadata);
    }

    public function test_admin_sees_empty_state(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMIN))
            ->get(route('cabinet.admin.audit'))
            ->assertOk()
            ->assertSee('Журнал действий')
            ->assertSee('Записей в журнале действий пока нет');
    }

    public function test_admin_sees_populated_events_newest_first(): void
    {
        $actor = User::factory()->create(['name' => 'Синтетический Администратор']);
        $this->travelTo('2026-10-20 10:00:00');
        $this->record(['reason' => 'first-event-reason'], $actor, AuditEventType::InquiryAssigned);
        $this->travelTo('2026-10-20 11:00:00');
        $this->record(['reason' => 'second-event-reason', 'urgent' => true, 'note' => null], $actor, AuditEventType::InquiryReassigned);

        $html = $this->actingAs($this->userWithRole(Role::ADMIN))
            ->get(route('cabinet.admin.audit'))->assertOk()->getContent();

        $this->assertStringContainsString('Обращение назначено', $html);
        $this->assertStringContainsString('Обращение переназначено', $html);
        $this->assertStringContainsString('Синтетический Администратор', $html);
        $this->assertStringContainsString('incoming_inquiry #11', $html);
        $this->assertStringContainsString('20.10.2026 11:00:00', $html);
        $this->assertStringContainsString('да', $html);
        $this->assertLessThan(strpos($html, 'first-event-reason'), strpos($html, 'second-event-reason'));
    }

    public function test_system_and_deleted_actor_render_without_error(): void
    {
        $this->record();
        $ghost = User::factory()->create();
        $this->record([], $ghost);
        DB::table('users')->where('id', $ghost->id)->delete();

        $html = $this->actingAs($this->userWithRole(Role::ADMIN))
            ->get(route('cabinet.admin.audit'))->assertOk()->getContent();

        $this->assertStringContainsString('система', $html);
        $this->assertStringContainsString('(#' . $ghost->id . ')', $html);
    }

    public function test_metadata_and_unknown_type_are_escaped(): void
    {
        $this->record(['reason' => '<script>alert(1)</script>']);
        // Строка, вставленная в обход рекордера (например, будущей версией кода).
        DB::table('audit_events')->insert([
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'event_type' => '<b>future.type</b>',
            'actor_id' => null,
            'subject_type' => '<i>x</i>',
            'subject_id' => 1,
            'metadata' => json_encode(['<u>k</u>' => '<img src=x onerror=alert(1)>', 'nested' => ['a' => 1]]),
            'occurred_at' => now(),
        ]);

        $html = $this->actingAs($this->userWithRole(Role::ADMIN))
            ->get(route('cabinet.admin.audit'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>future.type</b>', $html);
        $this->assertStringNotContainsString('<i>x</i>', $html);
        $this->assertStringNotContainsString('<u>k</u>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;future.type&lt;/b&gt;', $html);
    }

    public function test_pagination_is_bounded(): void
    {
        $recorder = $this->app->make(AuditRecorder::class);
        for ($i = 1; $i <= 45; $i++) {
            $recorder->record(AuditEventType::AdminAction, null, 'user', $i, ['seq' => 'seq-marker-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        }

        $admin = $this->userWithRole(Role::ADMIN);

        $first = $this->actingAs($admin)->get(route('cabinet.admin.audit'))->assertOk()->getContent();
        $this->assertSame(20, substr_count($first, 'seq-marker-'));
        $this->assertStringContainsString('seq-marker-045', $first);
        $this->assertStringNotContainsString('seq-marker-025', $first);
        $this->assertStringContainsString('page=2', $first);

        $third = $this->actingAs($admin)->get(route('cabinet.admin.audit', ['page' => 3]))->assertOk()->getContent();
        $this->assertSame(5, substr_count($third, 'seq-marker-'));
        $this->assertStringContainsString('seq-marker-001', $third);
    }

    public function test_page_has_no_mutating_controls_or_paths(): void
    {
        $this->record(['reason' => 'ordinary']);

        $html = $this->actingAs($this->userWithRole(Role::ADMIN))
            ->get(route('cabinet.admin.audit'))->assertOk()->getContent();

        $this->assertStringNotContainsString(base_path(), $html);
        $this->assertStringNotContainsString(storage_path(), $html);
        // Внутри основного содержимого нет форм: только чтение.
        $main = substr($html, (int) strpos($html, 'Журнал действий</h1>'));
        $this->assertStringNotContainsString('method="POST"', substr($main, 0, (int) strpos($main, '</main>') ?: null));
    }

    public function test_only_get_is_routed_so_events_cannot_be_changed_over_http(): void
    {
        $event = $this->record();
        $admin = $this->userWithRole(Role::ADMIN);
        $url = route('cabinet.admin.audit');

        $this->actingAs($admin)->post($url)->assertStatus(405);
        $this->actingAs($admin)->put($url . '/' . $event->id)->assertStatus(404);
        $this->actingAs($admin)->delete($url . '/' . $event->id)->assertStatus(404);
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_manager_is_denied_direct_url(): void
    {
        $this->record();

        $this->actingAs($this->userWithRole(Role::MANAGER))
            ->get(route('cabinet.admin.audit'))
            ->assertForbidden();
    }

    public function test_tourist_is_denied_direct_url(): void
    {
        $this->record();

        $this->actingAs($this->userWithRole(Role::TOURIST))
            ->get(route('cabinet.admin.audit'))
            ->assertForbidden();
    }

    public function test_user_with_manager_and_tourist_roles_is_denied(): void
    {
        $user = $this->userWithRole(Role::MANAGER);
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => 'tourist'])->id);

        $this->actingAs($user)->get(route('cabinet.admin.audit'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cabinet.admin.audit'))->assertRedirect(route('login'));
    }

    public function test_admin_sidebar_links_to_the_audit_page_and_other_roles_do_not_see_it(): void
    {
        $adminHtml = $this->actingAs($this->userWithRole(Role::ADMIN))
            ->get(route('cabinet.admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('href="' . route('cabinet.admin.audit') . '"', $adminHtml);

        $managerHtml = $this->actingAs($this->userWithRole(Role::MANAGER))
            ->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('cabinet.admin.audit'), $managerHtml);
    }
}
