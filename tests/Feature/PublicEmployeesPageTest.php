<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * E1-A2-F1: Public Employee Phone Link.
 *
 * Правило: видимый номер телефона сотрудника на странице /company/employees
 * (значение поля `tel`) и адрес назначения ссылки `tel:` обязаны совпадать.
 * До исправления href использовал `whatsapp ?? tel`, из-за чего при
 * различающихся значениях `tel` и `whatsapp` клик по видимому номеру звонил
 * на другой (whatsapp) номер.
 */
class PublicEmployeesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('employees_all');
    }

    private function createEmployeeWithDifferentTelAndWhatsapp(): Employee
    {
        return Employee::create([
            'name'     => 'Иван Иванов',
            'position' => 'Менеджер',
            'tel'      => '+78121112233',
            'whatsapp' => '+79997778899',
            'email'    => 'ivan@example.com',
            'vk'       => '',
        ]);
    }

    public function test_public_employees_page_is_reachable(): void
    {
        $response = $this->get(route('employees.index'));

        $response->assertOk();
    }

    public function test_visible_phone_matches_employee_tel(): void
    {
        $employee = $this->createEmployeeWithDifferentTelAndWhatsapp();

        $response = $this->get(route('employees.index'));

        $response->assertOk();
        $response->assertSee($employee->tel);
    }

    public function test_phone_anchor_hrefs_to_employee_tel_not_whatsapp(): void
    {
        $employee = $this->createEmployeeWithDifferentTelAndWhatsapp();

        $response = $this->get(route('employees.index'));

        $response->assertOk();
        $response->assertSee('tel:' . $employee->tel, false);
        $response->assertDontSee('tel:' . $employee->whatsapp, false);
    }
}
