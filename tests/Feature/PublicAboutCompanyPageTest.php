<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E1-A2-F2: Public About Company Payment/Refund Copy.
 *
 * Правило: страница /company/about_company не должна содержать устаревшее
 * описание оплаты через платёжный шлюз ПАО Сбербанк (Verified by Visa /
 * MasterCard SecureCode) и обязана отражать актуальные способы оплаты
 * (наличные, интернет-эквайринг, QR-код, оплата в офисе) и актуальные
 * условия возврата денежных средств на карту клиента.
 */
class PublicAboutCompanyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_about_company_page_is_reachable(): void
    {
        $response = $this->get(route('about_company.index'));

        $response->assertOk();
    }

    public function test_page_lists_current_payment_methods(): void
    {
        $response = $this->get(route('about_company.index'));

        $response->assertOk();
        $response->assertSee('наличными', false);
        $response->assertSee('интернет-эквайринг', false);
        $response->assertSee('QR-коду на расчётный счёт организации', false);
        $response->assertSee('картой через терминал в офисе', false);
    }

    public function test_page_describes_current_refund_conditions(): void
    {
        $response = $this->get(route('about_company.index'));

        $response->assertOk();
        $response->assertSee('Возврат денежных средств производится на банковскую карту клиента', false);
        $response->assertSee('отель не был подтверждён, оплаченная сумма возвращается в полном', false);
        $response->assertSee('туроператор может применить', false);
        $response->assertSee('определяются индивидуально в зависимости от', false);
    }

    public function test_page_no_longer_contains_obsolete_payment_gateway_copy(): void
    {
        $response = $this->get(route('about_company.index'));

        $response->assertOk();
        $response->assertDontSee('ПАО Сбербанк', false);
        $response->assertDontSee('Verified By Visa', false);
        $response->assertDontSee('MasterCard Secure Code', false);
    }
}
